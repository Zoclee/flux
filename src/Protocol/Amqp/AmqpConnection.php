<?php

declare(strict_types=1);

namespace Flux\Protocol\Amqp;

use Flux\Broker\Broker;
use Flux\Broker\TopologyException;
use Flux\Runtime\ConnectionRegistry;
use Flux\Runtime\RuntimeConnection;
use RuntimeException;

final class AmqpConnection
{
    public const PROTOCOL_HEADER = "AMQP\x00\x00\x09\x01";

    private AmqpConnectionState $state = AmqpConnectionState::AwaitingProtocolHeader;
    private string $headerBuffer = '';
    private FrameCodec $codec;
    private RuntimeConnection $runtimeConnection;

    /**
     * @var array<int, true>
     */
    private array $channels = [];

    /**
     * @param resource $socket
     */
    public function __construct(
        private mixed $socket,
        private readonly ConnectionRegistry $connections,
        private readonly int $maxFrameSize = 131072,
        private readonly ?Broker $broker = null
    ) {
        stream_set_blocking($this->socket, false);
        $this->codec = new FrameCodec($this->maxFrameSize);
        $this->runtimeConnection = RuntimeConnection::create(
            'amqp-0-9-1',
            @stream_socket_get_name($this->socket, true) ?: null,
            ['state' => $this->state->value]
        );
        $this->connections->add($this->runtimeConnection);
    }

    public function tick(): void
    {
        if ($this->state === AmqpConnectionState::Closed) {
            return;
        }

        while (!feof($this->socket)) {
            $bytes = fread($this->socket, 8192);
            if ($bytes === false || $bytes === '') {
                break;
            }

            try {
                $this->receive($bytes);
            } catch (ProtocolException) {
                $this->close();
                return;
            }
        }

        if (feof($this->socket)) {
            $this->close();
        }
    }

    public function receive(string $bytes): void
    {
        if ($this->state === AmqpConnectionState::AwaitingProtocolHeader) {
            $this->headerBuffer .= $bytes;

            if (strlen($this->headerBuffer) < strlen(self::PROTOCOL_HEADER)) {
                return;
            }

            $header = substr($this->headerBuffer, 0, strlen(self::PROTOCOL_HEADER));
            if ($header !== self::PROTOCOL_HEADER) {
                throw new ProtocolException('Unsupported AMQP protocol header.');
            }

            $remaining = substr($this->headerBuffer, strlen(self::PROTOCOL_HEADER));
            $this->headerBuffer = '';
            $this->state = AmqpConnectionState::Starting;
            $this->writeFrame($this->connectionStart());

            if ($remaining === '') {
                return;
            }

            $bytes = $remaining;
        }

        foreach ($this->codec->push($bytes) as $frame) {
            $this->handleFrame($frame);
        }
    }

    public function close(): void
    {
        if ($this->state === AmqpConnectionState::Closed) {
            return;
        }

        $this->state = AmqpConnectionState::Closing;
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->connections->remove($this->runtimeConnection->id);
        $this->state = AmqpConnectionState::Closed;
    }

    public function state(): AmqpConnectionState
    {
        return $this->state;
    }

    public function isClosed(): bool
    {
        return $this->state === AmqpConnectionState::Closed;
    }

    private function handleFrame(Frame $frame): void
    {
        [$classId, $methodId] = $frame->method();

        if ($this->state === AmqpConnectionState::Open) {
            $this->handleOpenConnectionFrame($frame, $classId, $methodId);
            return;
        }

        if ($frame->channel !== 0) {
            throw new ProtocolException('AMQP connection handshake must use channel 0.');
        }

        if ($this->state === AmqpConnectionState::Starting && $classId === 10 && $methodId === 11) {
            $this->state = AmqpConnectionState::Tuning;
            $this->writeFrame($this->connectionTune());
            return;
        }

        if ($this->state === AmqpConnectionState::Tuning && $classId === 10 && $methodId === 31) {
            $this->state = AmqpConnectionState::Opening;
            return;
        }

        if ($this->state === AmqpConnectionState::Opening && $classId === 10 && $methodId === 40) {
            $this->state = AmqpConnectionState::Open;
            $this->writeFrame($this->connectionOpenOk());
            return;
        }

        throw new ProtocolException('Unexpected AMQP method for current connection state.');
    }

    private function handleOpenConnectionFrame(Frame $frame, int $classId, int $methodId): void
    {
        if ($frame->channel === 0) {
            throw new ProtocolException('AMQP channel methods must not use channel 0.');
        }

        if ($classId === 20 && $methodId === 10) {
            $this->channels[$frame->channel] = true;
            $this->writeFrame(Frame::methodFrame($frame->channel, 20, 11, $this->longString('')));
            return;
        }

        if ($classId === 20 && $methodId === 40) {
            unset($this->channels[$frame->channel]);
            $this->writeFrame(Frame::methodFrame($frame->channel, 20, 41));
            return;
        }

        if ($classId === 20 && $methodId === 41) {
            unset($this->channels[$frame->channel]);
            return;
        }

        if (!isset($this->channels[$frame->channel])) {
            $this->sendChannelError($frame->channel, 504, 'CHANNEL_ERROR - channel is not open', $classId, $methodId);
            return;
        }

        try {
            match ([$classId, $methodId]) {
                [50, 10] => $this->handleQueueDeclare($frame),
                [40, 10] => $this->handleExchangeDeclare($frame),
                [50, 20] => $this->handleQueueBind($frame),
                default => $this->sendChannelError(
                    $frame->channel,
                    540,
                    'NOT_IMPLEMENTED - AMQP method is not supported by Flux yet',
                    $classId,
                    $methodId
                ),
            };
        } catch (TopologyException $exception) {
            $this->sendChannelError(
                $frame->channel,
                $this->replyCodeForTopologyException($exception),
                $exception->getMessage(),
                $classId,
                $methodId
            );
        } catch (RuntimeException $exception) {
            $this->sendChannelError($frame->channel, 541, $exception->getMessage(), $classId, $methodId);
        }
    }

    private function handleQueueDeclare(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShort();
        $queue = $reader->readShortString();
        $bits = $reader->readOctet();
        $passive = ($bits & 0b00000001) !== 0;
        $durable = ($bits & 0b00000010) !== 0;
        $exclusive = ($bits & 0b00000100) !== 0;
        $autoDelete = ($bits & 0b00001000) !== 0;
        $noWait = ($bits & 0b00010000) !== 0;
        $reader->skipTable();
        $reader->assertComplete();

        if ($exclusive) {
            throw new TopologyException('Exclusive queues are not supported yet.', TopologyException::NOT_IMPLEMENTED);
        }

        $destination = $this->broker()->declareQueue('/', $queue, $durable, $autoDelete, $passive);

        if (!$noWait) {
            $this->writeFrame(Frame::methodFrame(
                $frame->channel,
                50,
                11,
                $this->shortString($destination->name) . pack('NN', 0, 0)
            ));
        }
    }

    private function handleExchangeDeclare(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShort();
        $exchange = $reader->readShortString();
        $type = $reader->readShortString();
        $bits = $reader->readOctet();
        $passive = ($bits & 0b00000001) !== 0;
        $durable = ($bits & 0b00000010) !== 0;
        $autoDelete = ($bits & 0b00000100) !== 0;
        $internal = ($bits & 0b00001000) !== 0;
        $noWait = ($bits & 0b00010000) !== 0;
        $reader->skipTable();
        $reader->assertComplete();

        if ($exchange === '') {
            throw new TopologyException('The default AMQP exchange is implicit.', TopologyException::PRECONDITION_FAILED);
        }

        if ($type !== 'direct') {
            throw new TopologyException(sprintf('Exchange type "%s" is not supported.', $type), TopologyException::NOT_IMPLEMENTED);
        }

        if ($internal) {
            throw new TopologyException('Internal exchanges are not supported yet.', TopologyException::NOT_IMPLEMENTED);
        }

        $this->broker()->declareDirectRoutingSource('/', $exchange, $durable, $autoDelete, $passive);

        if (!$noWait) {
            $this->writeFrame(Frame::methodFrame($frame->channel, 40, 11));
        }
    }

    private function handleQueueBind(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShort();
        $queue = $reader->readShortString();
        $exchange = $reader->readShortString();
        $routingKey = $reader->readShortString();
        $bits = $reader->readOctet();
        $noWait = ($bits & 0b00000001) !== 0;
        $reader->skipTable();
        $reader->assertComplete();

        $this->broker()->bindQueue('/', $exchange, $queue, $routingKey);

        if (!$noWait) {
            $this->writeFrame(Frame::methodFrame($frame->channel, 50, 21));
        }
    }

    private function broker(): Broker
    {
        return $this->broker ?? throw new RuntimeException('AMQP topology operations require a broker.');
    }

    private function sendChannelError(int $channel, int $replyCode, string $replyText, int $classId, int $methodId): void
    {
        unset($this->channels[$channel]);
        $this->writeFrame(Frame::methodFrame(
            $channel,
            20,
            40,
            pack('n', $replyCode) . $this->shortString($this->truncateReplyText($replyText)) . pack('nn', $classId, $methodId)
        ));
    }

    private function replyCodeForTopologyException(TopologyException $exception): int
    {
        return match ($exception->reason) {
            TopologyException::NOT_FOUND => 404,
            TopologyException::NOT_IMPLEMENTED => 540,
            default => 406,
        };
    }

    private function truncateReplyText(string $replyText): string
    {
        return strlen($replyText) <= 255 ? $replyText : substr($replyText, 0, 255);
    }

    private function writeFrame(Frame $frame): void
    {
        $bytes = $this->codec->encode($frame);
        $written = 0;

        while ($written < strlen($bytes)) {
            $result = fwrite($this->socket, substr($bytes, $written));
            if ($result === false || $result === 0) {
                throw new ProtocolException('Could not write AMQP frame.');
            }

            $written += $result;
        }
    }

    private function connectionStart(): Frame
    {
        return Frame::methodFrame(
            0,
            10,
            10,
            "\x00\x09" . $this->table([]) . $this->longString('PLAIN') . $this->longString('en_US')
        );
    }

    private function connectionTune(): Frame
    {
        return Frame::methodFrame(0, 10, 30, pack('nNn', 0, $this->maxFrameSize, 0));
    }

    private function connectionOpenOk(): Frame
    {
        return Frame::methodFrame(0, 10, 41, $this->shortString(''));
    }

    /**
     * @param array<string, string> $values
     */
    private function table(array $values): string
    {
        $payload = '';
        foreach ($values as $key => $value) {
            $payload .= $this->shortString($key) . 'S' . $this->longString($value);
        }

        return pack('N', strlen($payload)) . $payload;
    }

    private function shortString(string $value): string
    {
        if (strlen($value) > 255) {
            throw new ProtocolException('AMQP short string is too long.');
        }

        return chr(strlen($value)) . $value;
    }

    private function longString(string $value): string
    {
        return pack('N', strlen($value)) . $value;
    }
}
