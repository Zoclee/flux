<?php

declare(strict_types=1);

namespace Flux\Protocol\Amqp;

use DateTimeImmutable;
use Flux\Broker\AcknowledgeRequest;
use Flux\Broker\Broker;
use Flux\Broker\Delivery;
use Flux\Broker\Message;
use Flux\Broker\PublishRequest;
use Flux\Broker\RejectRequest;
use Flux\Broker\ReleaseRequest;
use Flux\Broker\ReserveRequest;
use Flux\Broker\TopologyException;
use Flux\Runtime\ConnectionRegistry;
use Flux\Runtime\ConsumerRegistry;
use Flux\Runtime\RuntimeConnection;
use Flux\Runtime\RuntimeConsumer;
use Flux\Support\Uuid;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AmqpConnection
{
    public const PROTOCOL_HEADER = "AMQP\x00\x00\x09\x01";
    private const NANOS_PER_SECOND = 1_000_000_000;

    private AmqpConnectionState $state = AmqpConnectionState::AwaitingProtocolHeader;
    private string $headerBuffer = '';
    private FrameCodec $codec;
    private RuntimeConnection $runtimeConnection;
    private ConsumerRegistry $consumers;
    private int $negotiatedHeartbeat;
    private int $lastReceivedAt;
    private int $lastSentAt;

    /**
     * @var callable(): int
     */
    private $clock;

    /**
     * @var array<int, true>
     */
    private array $channels = [];

    /**
     * @var array<int, int>
     */
    private array $prefetchCounts = [];

    /**
     * @var array<int, true>
     */
    private array $confirmChannels = [];

    /**
     * @var array<int, int>
     */
    private array $nextPublishSequenceTags = [];

    /**
     * @var array<int, array{exchange: string, routing_key: string, mandatory: bool, immediate: bool}>
     */
    private array $pendingPublishMethods = [];

    /**
     * @var array<int, array{exchange: string, routing_key: string, body_size: int, properties: array<string, mixed>, body: string}>
     */
    private array $pendingPublishes = [];

    /**
     * @var array<string, array{consumer: RuntimeConsumer, channel: int, queue: string, no_ack: bool}>
     */
    private array $activeConsumers = [];

    /**
     * @var array<int, int>
     */
    private array $nextDeliveryTags = [];

    /**
     * @var array<int, array<int, array{delivery: Delivery, consumer_tag: string}>>
     */
    private array $unackedDeliveries = [];

    /**
     * @param resource $socket
     */
    public function __construct(
        private mixed $socket,
        private readonly ConnectionRegistry $connections,
        private readonly int $maxFrameSize = 131072,
        private readonly ?Broker $broker = null,
        ?ConsumerRegistry $consumers = null,
        private readonly int $maxMessageSize = 10485760,
        private readonly int $heartbeatInterval = 60,
        ?callable $clock = null
    ) {
        if ($this->heartbeatInterval < 0 || $this->heartbeatInterval > 65535) {
            throw new RuntimeException('AMQP heartbeat interval must fit in an unsigned short.');
        }

        stream_set_blocking($this->socket, false);
        $this->codec = new FrameCodec($this->maxFrameSize);
        $this->consumers = $consumers ?? new ConsumerRegistry();
        $this->clock = $clock ?? static fn (): int => hrtime(true);
        $this->lastReceivedAt = $this->now();
        $this->lastSentAt = $this->lastReceivedAt;
        $this->negotiatedHeartbeat = $this->heartbeatInterval;
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
            return;
        }

        if ($this->isHeartbeatTimedOut()) {
            error_log(sprintf('AMQP connection timed out: %s', $this->runtimeConnection->id));
            $this->close();
            return;
        }

        $this->deliverToConsumers();
        if ($this->state === AmqpConnectionState::Closed) {
            return;
        }

        $this->sendHeartbeatIfIdle();
    }

    public function receive(string $bytes): void
    {
        $receivedAt = $this->now();

        if ($this->state === AmqpConnectionState::AwaitingProtocolHeader) {
            $this->headerBuffer .= $bytes;

            if (strlen($this->headerBuffer) < strlen(self::PROTOCOL_HEADER)) {
                $this->lastReceivedAt = $receivedAt;
                return;
            }

            $header = substr($this->headerBuffer, 0, strlen(self::PROTOCOL_HEADER));
            if ($header !== self::PROTOCOL_HEADER) {
                throw new ProtocolException('Unsupported AMQP protocol header.');
            }

            $remaining = substr($this->headerBuffer, strlen(self::PROTOCOL_HEADER));
            $this->headerBuffer = '';
            $this->state = AmqpConnectionState::Starting;
            $this->lastReceivedAt = $receivedAt;
            $this->writeFrame($this->connectionStart());

            if ($remaining === '') {
                return;
            }

            $bytes = $remaining;
        }

        foreach ($this->codec->push($bytes) as $frame) {
            $this->handleFrame($frame);
            $this->lastReceivedAt = $receivedAt;
        }
    }

    public function close(): void
    {
        if ($this->state === AmqpConnectionState::Closed) {
            return;
        }

        $this->state = AmqpConnectionState::Closing;
        $this->releaseOutstandingDeliveries();
        foreach (array_keys($this->activeConsumers) as $consumerTag) {
            $this->removeConsumer($consumerTag);
        }

        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->connections->remove($this->runtimeConnection->id);
        $this->channels = [];
        $this->prefetchCounts = [];
        $this->confirmChannels = [];
        $this->nextPublishSequenceTags = [];
        $this->pendingPublishMethods = [];
        $this->pendingPublishes = [];
        $this->nextDeliveryTags = [];
        error_log(sprintf('AMQP connection closed: %s', $this->runtimeConnection->id));
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

    public function negotiatedHeartbeatInterval(): int
    {
        return $this->negotiatedHeartbeat;
    }

    private function handleFrame(Frame $frame): void
    {
        if ($frame->type === Frame::TYPE_HEARTBEAT) {
            if ($frame->channel !== 0 || $frame->payload !== '') {
                throw new ProtocolException('AMQP heartbeat frames must use channel 0 with an empty payload.');
            }

            return;
        }

        if ($frame->type === Frame::TYPE_HEADER || $frame->type === Frame::TYPE_BODY) {
            $this->handleContentFrame($frame);
            return;
        }

        [$classId, $methodId] = $frame->method();

        if ($this->state === AmqpConnectionState::Open && $frame->channel === 0) {
            $this->handleOpenConnectionControlFrame($classId, $methodId);
            return;
        }

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
            $this->negotiateHeartbeat($frame);
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

    private function handleOpenConnectionControlFrame(int $classId, int $methodId): void
    {
        if ($classId === 10 && $methodId === 50) {
            $this->writeFrame(Frame::methodFrame(0, 10, 51));
            $this->close();
            return;
        }

        if ($classId === 10 && $methodId === 51) {
            $this->close();
            return;
        }

        throw new ProtocolException('Unexpected AMQP connection method for open connection.');
    }

    private function handleOpenConnectionFrame(Frame $frame, int $classId, int $methodId): void
    {
        if ($classId === 20 && $methodId === 10) {
            $this->channels[$frame->channel] = true;
            $this->writeFrame(Frame::methodFrame($frame->channel, 20, 11, $this->longString('')));
            return;
        }

        if ($classId === 20 && $methodId === 40) {
            $this->closeChannel($frame->channel);
            $this->writeFrame(Frame::methodFrame($frame->channel, 20, 41));
            return;
        }

        if ($classId === 20 && $methodId === 41) {
            $this->closeChannel($frame->channel);
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
                [85, 10] => $this->handleConfirmSelect($frame),
                [60, 10] => $this->handleBasicQos($frame),
                [60, 40] => $this->handleBasicPublish($frame),
                [60, 20] => $this->handleBasicConsume($frame),
                [60, 80] => $this->handleBasicAck($frame),
                [60, 90] => $this->handleBasicReject($frame),
                [60, 120] => $this->handleBasicNack($frame),
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

    private function handleBasicQos(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $prefetchSize = $reader->readLong();
        $prefetchCount = $reader->readShort();
        $bits = $reader->readOctet();
        $reader->assertComplete();

        if ($prefetchSize !== 0) {
            $this->sendChannelError($frame->channel, 540, 'NOT_IMPLEMENTED - basic.qos prefetch-size is not supported', 60, 10);
            return;
        }

        if (($bits & 0b00000001) !== 0) {
            $this->sendChannelError($frame->channel, 540, 'NOT_IMPLEMENTED - basic.qos global=true is not supported', 60, 10);
            return;
        }

        $this->prefetchCounts[$frame->channel] = $prefetchCount;
        $this->writeFrame(Frame::methodFrame($frame->channel, 60, 11));
        $this->deliverToConsumers();
    }

    private function handleConfirmSelect(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $bits = $reader->readOctet();
        $reader->assertComplete();

        $this->confirmChannels[$frame->channel] = true;
        $this->nextPublishSequenceTags[$frame->channel] ??= 1;

        if (($bits & 0b00000001) === 0) {
            $this->writeFrame(Frame::methodFrame($frame->channel, 85, 11));
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

    private function handleBasicPublish(Frame $frame): void
    {
        if (isset($this->pendingPublishMethods[$frame->channel]) || isset($this->pendingPublishes[$frame->channel])) {
            throw new ProtocolException('AMQP publish content sequence is already in progress.');
        }

        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShort();
        $exchange = $reader->readShortString();
        $routingKey = $reader->readShortString();
        $bits = $reader->readOctet();
        $reader->assertComplete();

        $this->pendingPublishMethods[$frame->channel] = [
            'exchange' => $exchange,
            'routing_key' => $routingKey,
            'mandatory' => ($bits & 0b00000001) !== 0,
            'immediate' => ($bits & 0b00000010) !== 0,
        ];
    }

    private function handleBasicConsume(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShort();
        $queue = $reader->readShortString();
        $consumerTag = $reader->readShortString();
        $bits = $reader->readOctet();
        $noLocal = ($bits & 0b00000001) !== 0;
        $noAck = ($bits & 0b00000010) !== 0;
        $exclusive = ($bits & 0b00000100) !== 0;
        $noWait = ($bits & 0b00001000) !== 0;
        $reader->skipTable();
        $reader->assertComplete();

        if ($queue === '') {
            throw new TopologyException('Consumer queue must not be empty.', TopologyException::PRECONDITION_FAILED);
        }

        if ($noLocal || $exclusive) {
            throw new TopologyException('no-local and exclusive consumers are not supported yet.', TopologyException::NOT_IMPLEMENTED);
        }

        if ($consumerTag === '') {
            $consumerTag = sprintf('ctag-%s-%d', $this->runtimeConnection->id, count($this->activeConsumers) + 1);
        }

        if (isset($this->activeConsumers[$consumerTag])) {
            throw new TopologyException(sprintf('Consumer tag "%s" is already active.', $consumerTag), TopologyException::PRECONDITION_FAILED);
        }

        $this->broker()->ensureQueueSubscription('/', $queue, 'amqp');
        $consumer = RuntimeConsumer::create(
            $this->runtimeConnection->id,
            '/',
            $queue,
            'amqp',
            ['protocol' => 'amqp-0-9-1', 'channel' => $frame->channel, 'consumer_tag' => $consumerTag]
        );
        $this->consumers->add($consumer);
        $this->activeConsumers[$consumerTag] = [
            'consumer' => $consumer,
            'channel' => $frame->channel,
            'queue' => $queue,
            'no_ack' => $noAck,
        ];

        if (!$noWait) {
            $this->writeFrame(Frame::methodFrame($frame->channel, 60, 21, $this->shortString($consumerTag)));
        }

        $this->deliverToConsumers();
    }

    private function handleBasicAck(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $deliveryTag = $reader->readLongLong();
        $bits = $reader->readOctet();
        $reader->assertComplete();

        if (($bits & 0b00000001) !== 0) {
            $this->sendChannelError($frame->channel, 540, 'NOT_IMPLEMENTED - basic.ack multiple=true is not supported', 60, 80);
            return;
        }

        $mapping = $this->unackedDeliveries[$frame->channel][$deliveryTag] ?? null;
        if ($mapping === null) {
            $this->sendChannelError($frame->channel, 406, 'PRECONDITION_FAILED - unknown delivery tag', 60, 80);
            return;
        }

        $this->broker()->acknowledge(new AcknowledgeRequest($mapping['delivery']->id));
        unset($this->unackedDeliveries[$frame->channel][$deliveryTag]);
        $this->clearEmptyUnackedChannel($frame->channel);
        $this->deliverToConsumers();
    }

    private function handleBasicReject(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $deliveryTag = $reader->readLongLong();
        $bits = $reader->readOctet();
        $reader->assertComplete();

        $mapping = $this->unackedDeliveries[$frame->channel][$deliveryTag] ?? null;
        if ($mapping === null) {
            $this->sendChannelError($frame->channel, 406, 'PRECONDITION_FAILED - unknown delivery tag', 60, 90);
            return;
        }

        if (($bits & 0b00000001) !== 0) {
            $this->broker()->release(new ReleaseRequest($mapping['delivery']->id));
        } else {
            $this->broker()->reject(new RejectRequest($mapping['delivery']->id));
        }

        unset($this->unackedDeliveries[$frame->channel][$deliveryTag]);
        $this->clearEmptyUnackedChannel($frame->channel);
        $this->deliverToConsumers();
    }

    private function handleBasicNack(Frame $frame): void
    {
        $reader = new AmqpMethodReader(substr($frame->payload, 4));
        $deliveryTag = $reader->readLongLong();
        $bits = $reader->readOctet();
        $reader->assertComplete();

        if (($bits & 0b00000001) !== 0) {
            $this->sendChannelError($frame->channel, 540, 'NOT_IMPLEMENTED - basic.nack multiple=true is not supported', 60, 120);
            return;
        }

        $mapping = $this->unackedDeliveries[$frame->channel][$deliveryTag] ?? null;
        if ($mapping === null) {
            $this->sendChannelError($frame->channel, 406, 'PRECONDITION_FAILED - unknown delivery tag', 60, 120);
            return;
        }

        if (($bits & 0b00000010) !== 0) {
            $this->broker()->release(new ReleaseRequest($mapping['delivery']->id));
        } else {
            $this->broker()->reject(new RejectRequest($mapping['delivery']->id));
        }

        unset($this->unackedDeliveries[$frame->channel][$deliveryTag]);
        $this->clearEmptyUnackedChannel($frame->channel);
        $this->deliverToConsumers();
    }

    private function handleContentFrame(Frame $frame): void
    {
        if ($this->state !== AmqpConnectionState::Open || !isset($this->channels[$frame->channel])) {
            throw new ProtocolException('AMQP content frame arrived on an invalid channel.');
        }

        if ($frame->type === Frame::TYPE_HEADER) {
            $this->handleContentHeader($frame);
            return;
        }

        $this->handleContentBody($frame);
    }

    private function handleContentHeader(Frame $frame): void
    {
        $method = $this->pendingPublishMethods[$frame->channel] ?? null;
        if ($method === null || isset($this->pendingPublishes[$frame->channel])) {
            throw new ProtocolException('AMQP content header arrived without a pending publish.');
        }

        $reader = new AmqpMethodReader($frame->payload);
        $classId = $reader->readShort();
        $reader->readShort();
        $bodySize = $reader->readLongLong();
        if ($classId !== 60) {
            throw new ProtocolException('AMQP content header class is not supported.');
        }

        if ($bodySize > $this->maxMessageSize) {
            throw new ProtocolException('AMQP message body exceeds configured message size limit.');
        }

        $properties = $this->readBasicProperties($reader);
        $reader->assertComplete();
        unset($this->pendingPublishMethods[$frame->channel]);
        $this->pendingPublishes[$frame->channel] = [
            'exchange' => $method['exchange'],
            'routing_key' => $method['routing_key'],
            'body_size' => $bodySize,
            'properties' => $properties,
            'body' => '',
        ];

        if ($bodySize === 0) {
            $this->completePublish($frame->channel);
        }
    }

    private function handleContentBody(Frame $frame): void
    {
        if (!isset($this->pendingPublishes[$frame->channel])) {
            throw new ProtocolException('AMQP content body arrived without a content header.');
        }

        $publish = &$this->pendingPublishes[$frame->channel];
        $newSize = strlen($publish['body']) + strlen($frame->payload);
        if ($newSize > $publish['body_size'] || $newSize > $this->maxMessageSize) {
            throw new ProtocolException('AMQP content body byte count exceeds declared size.');
        }

        $publish['body'] .= $frame->payload;
        if (strlen($publish['body']) === $publish['body_size']) {
            unset($publish);
            $this->completePublish($frame->channel);
        }
    }

    private function completePublish(int $channel): void
    {
        $publish = $this->pendingPublishes[$channel] ?? throw new ProtocolException('No AMQP publish content is pending.');
        unset($this->pendingPublishes[$channel]);

        $properties = $publish['properties'];
        $messageId = $properties['message_id'] ?? null;
        if (is_string($messageId)) {
            try {
                Uuid::assertValid($messageId, 'Message ID');
            } catch (InvalidArgumentException) {
                $messageId = null;
            }
        } else {
            $messageId = null;
        }

        if ($publish['exchange'] === '') {
            try {
                $this->broker()->publishToDefaultExchange(
                    '/',
                    $publish['routing_key'],
                    $publish['body'],
                    $properties['headers'] ?? [],
                    $properties['content_type'] ?? null,
                    $properties['content_encoding'] ?? null,
                    $properties['priority'] ?? 0,
                ($properties['delivery_mode'] ?? 2) === 2,
                $messageId
            );
            $this->sendPublishConfirm($channel);
        } catch (TopologyException $exception) {
            $this->sendChannelError($channel, $this->replyCodeForTopologyException($exception), $exception->getMessage(), 60, 40);
        } catch (Throwable $exception) {
            $this->sendChannelError($channel, 541, $exception->getMessage(), 60, 40);
            }
            return;
        }

        try {
            $this->broker()->publish(new PublishRequest(
                '/',
                $publish['exchange'],
                $publish['routing_key'],
                $publish['body'],
                $properties['headers'] ?? [],
                $properties['content_type'] ?? null,
                $properties['content_encoding'] ?? null,
                $properties['priority'] ?? 0,
                ($properties['delivery_mode'] ?? 2) === 2,
                $messageId
            ));
            $this->sendPublishConfirm($channel);
        } catch (TopologyException $exception) {
            $this->sendChannelError($channel, $this->replyCodeForTopologyException($exception), $exception->getMessage(), 60, 40);
        } catch (Throwable $exception) {
            $this->sendChannelError($channel, 541, $exception->getMessage(), 60, 40);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readBasicProperties(AmqpMethodReader $reader): array
    {
        $flags = [];
        do {
            $flagWord = $reader->readShort();
            $flags[] = $flagWord;
        } while (($flagWord & 1) !== 0);

        $first = $flags[0] ?? 0;
        $properties = [];

        if (($first & 0b1000000000000000) !== 0) {
            $properties['content_type'] = $reader->readShortString();
        }
        if (($first & 0b0100000000000000) !== 0) {
            $properties['content_encoding'] = $reader->readShortString();
        }
        if (($first & 0b0010000000000000) !== 0) {
            $properties['headers'] = $reader->readTable();
        }
        if (($first & 0b0001000000000000) !== 0) {
            $properties['delivery_mode'] = $reader->readOctet();
        }
        if (($first & 0b0000100000000000) !== 0) {
            $properties['priority'] = $reader->readOctet();
        }
        if (($first & 0b0000010000000000) !== 0) {
            $reader->readShortString();
        }
        if (($first & 0b0000001000000000) !== 0) {
            $reader->readShortString();
        }
        if (($first & 0b0000000100000000) !== 0) {
            $reader->readShortString();
        }
        if (($first & 0b0000000010000000) !== 0) {
            $properties['message_id'] = $reader->readShortString();
        }
        if (($first & 0b0000000001000000) !== 0) {
            $reader->readLongLong();
        }
        if (($first & 0b0000000000100000) !== 0) {
            $reader->readShortString();
        }
        if (($first & 0b0000000000010000) !== 0) {
            $reader->readShortString();
        }
        if (($first & 0b0000000000001000) !== 0) {
            $reader->readShortString();
        }
        if (($first & 0b0000000000000100) !== 0) {
            $reader->readShortString();
        }

        return $properties;
    }

    private function deliverToConsumers(): void
    {
        foreach ($this->activeConsumers as $consumerTag => $state) {
            $channel = $state['channel'];
            if (!isset($this->channels[$channel])) {
                continue;
            }

            if (!$this->consumerHasPrefetchCapacity($channel, $consumerTag)) {
                continue;
            }

            $deliveryTag = $this->nextDeliveryTags[$channel] ?? 1;
            $delivery = $this->broker()->reserve(new ReserveRequest(
                $state['consumer']->virtualHost,
                $state['queue'],
                $state['consumer']->subscription,
                $state['consumer']->id,
                (string) $deliveryTag
            ));

            if ($delivery === null) {
                continue;
            }

            $message = $this->broker()->messageForDelivery($delivery);
            $this->sendDelivery($channel, $consumerTag, $deliveryTag, $delivery, $message, $state['queue']);
            $this->nextDeliveryTags[$channel] = $deliveryTag + 1;

            if ($state['no_ack']) {
                $this->broker()->acknowledge(new AcknowledgeRequest($delivery->id));
            } else {
                $this->unackedDeliveries[$channel][$deliveryTag] = [
                    'delivery' => $delivery,
                    'consumer_tag' => $consumerTag,
                ];
            }
        }
    }

    private function sendDelivery(
        int $channel,
        string $consumerTag,
        int $deliveryTag,
        Delivery $delivery,
        Message $message,
        string $queue
    ): void {
        $this->writeFrame(Frame::methodFrame(
            $channel,
            60,
            60,
            $this->shortString($consumerTag)
                . $this->packLongLong($deliveryTag)
                . chr($delivery->attempts > 1 ? 1 : 0)
                . $this->shortString('')
                . $this->shortString($queue)
        ));
        $this->writeFrame(new Frame(Frame::TYPE_HEADER, $channel, $this->contentHeader($message)));

        foreach (str_split($message->payload, max(1, $this->maxFrameSize)) as $chunk) {
            $this->writeFrame(new Frame(Frame::TYPE_BODY, $channel, $chunk));
        }
    }

    private function contentHeader(Message $message): string
    {
        $flags = 0;
        $values = '';

        if ($message->contentType !== null) {
            $flags |= 0b1000000000000000;
            $values .= $this->shortString($message->contentType);
        }
        if ($message->contentEncoding !== null) {
            $flags |= 0b0100000000000000;
            $values .= $this->shortString($message->contentEncoding);
        }
        if ($message->headers !== []) {
            $flags |= 0b0010000000000000;
            $values .= $this->fieldTable($message->headers);
        }

        $flags |= 0b0001000000000000;
        $values .= chr($message->persistent ? 2 : 1);
        $flags |= 0b0000100000000000;
        $values .= chr($message->priority);
        $flags |= 0b0000000010000000;
        $values .= $this->shortString($message->messageId);

        return pack('nn', 60, 0) . $this->packLongLong(strlen($message->payload)) . pack('n', $flags) . $values;
    }

    private function sendPublishConfirm(int $channel): void
    {
        if (!isset($this->confirmChannels[$channel])) {
            return;
        }

        $deliveryTag = $this->nextPublishSequenceTags[$channel] ?? 1;
        $this->nextPublishSequenceTags[$channel] = $deliveryTag + 1;
        $this->writeFrame(Frame::methodFrame($channel, 60, 80, $this->packLongLong($deliveryTag) . "\x00"));
    }

    private function closeChannel(int $channel): void
    {
        unset(
            $this->channels[$channel],
            $this->prefetchCounts[$channel],
            $this->confirmChannels[$channel],
            $this->nextPublishSequenceTags[$channel],
            $this->pendingPublishMethods[$channel],
            $this->pendingPublishes[$channel]
        );

        foreach ($this->activeConsumers as $consumerTag => $state) {
            if ($state['channel'] === $channel) {
                $this->removeConsumer($consumerTag);
            }
        }

        $this->releaseOutstandingDeliveries($channel);
    }

    private function removeConsumer(string $consumerTag): void
    {
        $state = $this->activeConsumers[$consumerTag] ?? null;
        if ($state === null) {
            return;
        }

        $this->consumers->remove($state['consumer']->id);
        unset($this->activeConsumers[$consumerTag]);
    }

    private function releaseOutstandingDeliveries(?int $channel = null): void
    {
        $released = 0;

        foreach ($this->unackedDeliveries as $deliveryChannel => $deliveries) {
            if ($channel !== null && $channel !== $deliveryChannel) {
                continue;
            }

            foreach ($deliveries as $deliveryTag => $mapping) {
                try {
                    $this->broker()->release(new ReleaseRequest($mapping['delivery']->id));
                    $released++;
                } catch (RuntimeException) {
                }
                unset($this->unackedDeliveries[$deliveryChannel][$deliveryTag]);
            }

            if (($this->unackedDeliveries[$deliveryChannel] ?? []) === []) {
                unset($this->unackedDeliveries[$deliveryChannel]);
            }
        }

        if ($released > 0) {
            error_log(sprintf('AMQP unacked deliveries released: %d', $released));
        }
    }

    private function consumerHasPrefetchCapacity(int $channel, string $consumerTag): bool
    {
        $prefetchCount = $this->prefetchCounts[$channel] ?? 0;
        if ($prefetchCount === 0) {
            return true;
        }

        return $this->unackedCount($channel, $consumerTag) < $prefetchCount;
    }

    private function unackedCount(int $channel, string $consumerTag): int
    {
        $count = 0;
        foreach ($this->unackedDeliveries[$channel] ?? [] as $mapping) {
            if ($mapping['consumer_tag'] === $consumerTag) {
                $count++;
            }
        }

        return $count;
    }

    private function clearEmptyUnackedChannel(int $channel): void
    {
        if (($this->unackedDeliveries[$channel] ?? []) === []) {
            unset($this->unackedDeliveries[$channel]);
        }
    }

    private function broker(): Broker
    {
        return $this->broker ?? throw new RuntimeException('AMQP topology operations require a broker.');
    }

    private function sendChannelError(int $channel, int $replyCode, string $replyText, int $classId, int $methodId): void
    {
        $this->closeChannel($channel);
        $this->writeFrame(Frame::methodFrame(
            $channel,
            20,
            40,
            pack('n', $replyCode) . $this->shortString($this->truncateReplyText($replyText)) . pack('nn', $classId, $methodId)
        ));
    }

    private function negotiateHeartbeat(Frame $frame): void
    {
        if (strlen($frame->payload) < 12) {
            throw new ProtocolException('AMQP tune-ok payload is incomplete.');
        }

        $values = unpack('nchannelMax/NframeMax/nheartbeat', substr($frame->payload, 4, 8));
        $clientHeartbeat = (int) $values['heartbeat'];

        if ($this->heartbeatInterval === 0 || $clientHeartbeat === 0) {
            $this->negotiatedHeartbeat = 0;
            return;
        }

        $this->negotiatedHeartbeat = min($this->heartbeatInterval, $clientHeartbeat);
    }

    private function isHeartbeatTimedOut(): bool
    {
        if ($this->negotiatedHeartbeat === 0 || $this->state !== AmqpConnectionState::Open) {
            return false;
        }

        return $this->elapsedSecondsSince($this->lastReceivedAt) >= ($this->negotiatedHeartbeat * 2);
    }

    private function sendHeartbeatIfIdle(): void
    {
        if ($this->negotiatedHeartbeat === 0 || $this->state !== AmqpConnectionState::Open) {
            return;
        }

        if ($this->elapsedSecondsSince($this->lastSentAt) < $this->negotiatedHeartbeat) {
            return;
        }

        $this->writeFrame(Frame::heartbeatFrame());
    }

    private function elapsedSecondsSince(int $nanos): float
    {
        return ($this->now() - $nanos) / self::NANOS_PER_SECOND;
    }

    private function now(): int
    {
        return ($this->clock)();
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

        $this->lastSentAt = $this->now();
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
        return Frame::methodFrame(0, 10, 30, pack('nNn', 0, $this->maxFrameSize, $this->heartbeatInterval));
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

    /**
     * @param array<string, mixed> $values
     */
    private function fieldTable(array $values): string
    {
        $payload = '';
        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $payload .= $this->shortString($key);
            if (is_bool($value)) {
                $payload .= 't' . chr($value ? 1 : 0);
            } elseif (is_int($value)) {
                $payload .= 'I' . pack('N', $value);
            } elseif ($value === null) {
                $payload .= 'V';
            } else {
                $payload .= 'S' . $this->longString((string) $value);
            }
        }

        return pack('N', strlen($payload)) . $payload;
    }

    private function packLongLong(int $value): string
    {
        if ($value < 0) {
            throw new ProtocolException('AMQP long-long cannot be negative.');
        }

        $high = intdiv($value, 4294967296);
        $low = $value % 4294967296;

        return pack('NN', $high, $low);
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
