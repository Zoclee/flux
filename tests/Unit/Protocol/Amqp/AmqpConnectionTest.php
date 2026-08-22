<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Protocol\Amqp;

use Flux\Protocol\Amqp\AmqpConnection;
use Flux\Protocol\Amqp\AmqpConnectionState;
use Flux\Protocol\Amqp\Frame;
use Flux\Protocol\Amqp\FrameCodec;
use Flux\Protocol\Amqp\ProtocolException;
use Flux\Runtime\ConnectionRegistry;
use PHPUnit\Framework\TestCase;

final class AmqpConnectionTest extends TestCase
{
    public function testProtocolHeaderStartsHandshake(): void
    {
        [$connection, $socket] = $this->connection();

        $connection->receive(AmqpConnection::PROTOCOL_HEADER);

        self::assertSame(AmqpConnectionState::Starting, $connection->state());
        self::assertSame([10, 10], $this->writtenMethods($socket)[0]);
    }

    public function testProtocolHeaderCanArrivePartially(): void
    {
        [$connection, $socket] = $this->connection();

        $connection->receive(substr(AmqpConnection::PROTOCOL_HEADER, 0, 4));
        self::assertSame(AmqpConnectionState::AwaitingProtocolHeader, $connection->state());

        $connection->receive(substr(AmqpConnection::PROTOCOL_HEADER, 4));

        self::assertSame(AmqpConnectionState::Starting, $connection->state());
        self::assertSame([10, 10], $this->writtenMethods($socket)[0]);
    }

    public function testInvalidProtocolHeaderIsRejected(): void
    {
        [$connection] = $this->connection();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unsupported AMQP protocol header.');

        $connection->receive("AMQP\x00\x00\x09\x00");
    }

    public function testConnectionHandshakeStateTransitions(): void
    {
        [$connection, $socket] = $this->connection();
        $codec = new FrameCodec();

        $connection->receive(AmqpConnection::PROTOCOL_HEADER);
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 11)));
        self::assertSame(AmqpConnectionState::Tuning, $connection->state());

        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 31, $this->tuneOk(60))));
        self::assertSame(AmqpConnectionState::Opening, $connection->state());
        self::assertSame(60, $connection->negotiatedHeartbeatInterval());

        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 40)));
        self::assertSame(AmqpConnectionState::Open, $connection->state());

        self::assertSame([[10, 10], [10, 30], [10, 41]], $this->writtenMethods($socket));
    }

    public function testUnexpectedMethodForStateIsRejected(): void
    {
        [$connection] = $this->connection();
        $codec = new FrameCodec();
        $connection->receive(AmqpConnection::PROTOCOL_HEADER);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unexpected AMQP method for current connection state.');

        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 40)));
    }

    public function testHandshakeMethodsMustUseChannelZero(): void
    {
        [$connection] = $this->connection();
        $codec = new FrameCodec();
        $connection->receive(AmqpConnection::PROTOCOL_HEADER);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('AMQP connection handshake must use channel 0.');

        $connection->receive($codec->encode(Frame::methodFrame(1, 10, 11)));
    }

    public function testChannelCanOpenAndCloseAfterConnectionHandshake(): void
    {
        [$connection, $socket] = $this->connection();
        $codec = new FrameCodec();
        $this->completeHandshake($connection);

        $connection->receive($codec->encode(Frame::methodFrame(1, 20, 10, "\x00")));
        $connection->receive($codec->encode(Frame::methodFrame(1, 20, 40, pack('n', 200) . "\x00" . pack('nn', 0, 0))));

        self::assertSame(
            [[10, 10], [10, 30], [10, 41], [20, 11], [20, 41]],
            $this->writtenMethods($socket)
        );
    }

    public function testMultipleChannelsCanBeOpenedOnOneConnection(): void
    {
        [$connection, $socket] = $this->connection();
        $codec = new FrameCodec();
        $this->completeHandshake($connection);

        $connection->receive($codec->encode(Frame::methodFrame(1, 20, 10, "\x00")));
        $connection->receive($codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));

        self::assertSame(
            [[10, 10], [10, 30], [10, 41], [20, 11], [20, 11]],
            $this->writtenMethods($socket)
        );
    }

    public function testOperationOnUnopenedChannelGetsChannelClose(): void
    {
        [$connection, $socket] = $this->connection();
        $codec = new FrameCodec();
        $this->completeHandshake($connection);

        $connection->receive($codec->encode(Frame::methodFrame(9, 50, 10, pack('n', 0) . "\x06orders" . "\x00" . pack('N', 0))));

        $methods = $this->writtenMethods($socket);
        self::assertSame([20, 40], $methods[count($methods) - 1]);
    }

    public function testMalformedMethodFrameIsRejected(): void
    {
        [$connection] = $this->connection();
        $this->completeHandshake($connection);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Expected a complete AMQP method frame.');

        $connection->receive((new FrameCodec())->encode(new Frame(Frame::TYPE_METHOD, 1, "\x00")));
    }

    public function testHeartbeatValueNegotiatesToLowerNonZeroInterval(): void
    {
        [$connection, $socket] = $this->connection(60);
        $codec = new FrameCodec();

        $connection->receive(AmqpConnection::PROTOCOL_HEADER);
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 11)));
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 31, $this->tuneOk(10))));

        self::assertSame(10, $connection->negotiatedHeartbeatInterval());
        self::assertSame(60, $this->heartbeatFromTune($socket));
    }

    public function testHeartbeatCanBeDisabledWithZero(): void
    {
        [$connection] = $this->connection(60);
        $codec = new FrameCodec();

        $connection->receive(AmqpConnection::PROTOCOL_HEADER);
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 11)));
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 31, $this->tuneOk(0))));

        self::assertSame(0, $connection->negotiatedHeartbeatInterval());
    }

    public function testMalformedHeartbeatFrameIsRejected(): void
    {
        [$connection] = $this->connection();
        $this->completeHandshake($connection);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('AMQP heartbeat frames must use channel 0 with an empty payload.');

        $connection->receive((new FrameCodec())->encode(new Frame(Frame::TYPE_HEARTBEAT, 1, 'x')));
    }

    /**
     * @return array{0: AmqpConnection, 1: resource}
     */
    private function connection(int $heartbeatInterval = 60): array
    {
        $socket = fopen('php://temp', 'w+');
        self::assertIsResource($socket);

        return [new AmqpConnection($socket, new ConnectionRegistry(), heartbeatInterval: $heartbeatInterval), $socket];
    }

    /**
     * @param resource $socket
     * @return list<array{0: int, 1: int}>
     */
    private function writtenMethods(mixed $socket): array
    {
        rewind($socket);
        $bytes = stream_get_contents($socket);
        self::assertIsString($bytes);

        $methods = [];
        $codec = new FrameCodec();
        foreach ($codec->push($bytes) as $frame) {
            $methods[] = $frame->method();
        }

        return $methods;
    }

    private function completeHandshake(AmqpConnection $connection): void
    {
        $codec = new FrameCodec();
        $connection->receive(AmqpConnection::PROTOCOL_HEADER);
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 11)));
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 31, $this->tuneOk(60))));
        $connection->receive($codec->encode(Frame::methodFrame(0, 10, 40)));
        self::assertSame(AmqpConnectionState::Open, $connection->state());
    }

    private function tuneOk(int $heartbeat): string
    {
        return pack('nNn', 0, 131072, $heartbeat);
    }

    /**
     * @param resource $socket
     */
    private function heartbeatFromTune(mixed $socket): int
    {
        rewind($socket);
        $bytes = stream_get_contents($socket);
        self::assertIsString($bytes);

        foreach ((new FrameCodec())->push($bytes) as $frame) {
            if ($frame->method() !== [10, 30]) {
                continue;
            }

            $values = unpack('nchannelMax/NframeMax/nheartbeat', substr($frame->payload, 4, 8));

            return (int) $values['heartbeat'];
        }

        self::fail('connection.tune was not written.');
    }
}
