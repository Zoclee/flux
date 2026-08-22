<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Protocol\Amqp;

use Flux\Protocol\Amqp\AmqpConnection;
use Flux\Protocol\Amqp\AmqpListener;
use Flux\Protocol\Amqp\Frame;
use Flux\Protocol\Amqp\FrameCodec;
use Flux\Runtime\ConnectionRegistry;
use PHPUnit\Framework\TestCase;

final class AmqpListenerTest extends TestCase
{
    public function testListenerCompletesConnectionHandshakeOverTcp(): void
    {
        $runtimeConnections = new ConnectionRegistry();
        $listener = new AmqpListener($runtimeConnections, '127.0.0.1', 0);
        $listener->start();

        try {
            $client = $this->connect($listener);
            $this->connectAmqp($listener, $client, 60);
            self::assertSame(1, $runtimeConnections->count());
            fclose($client);
        } finally {
            $listener->stop();
        }

        self::assertSame(0, $runtimeConnections->count());
    }

    public function testListenerRejectsInvalidProtocolHeaderCleanly(): void
    {
        $listener = new AmqpListener(new ConnectionRegistry(), '127.0.0.1', 0);
        $listener->start();

        try {
            $client = $this->connect($listener);
            fwrite($client, "AMQP\x00\x00\x09\x00");

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $listener->tick();
                if (feof($client)) {
                    break;
                }
                usleep(1000);
            }

            self::assertTrue(feof($client));
            fclose($client);
        } finally {
            $listener->stop();
        }
    }

    public function testIdleConnectionSendsHeartbeatFrame(): void
    {
        $now = 0;
        $listener = new AmqpListener(
            new ConnectionRegistry(),
            '127.0.0.1',
            0,
            heartbeatInterval: 5,
            clock: static function () use (&$now): int {
                return $now * 1_000_000_000;
            }
        );
        $listener->start();

        try {
            $client = $this->connect($listener);
            $this->connectAmqp($listener, $client, 5);

            $now = 4;
            $listener->tick();
            self::assertSame('', fread($client, 8192));

            $now = 5;
            $frame = $this->readFrame($listener, $client);

            self::assertSame(Frame::TYPE_HEARTBEAT, $frame->type);
            self::assertSame(0, $frame->channel);
            self::assertSame('', $frame->payload);
            fclose($client);
        } finally {
            $listener->stop();
        }
    }

    public function testOutboundTrafficDelaysUnnecessaryHeartbeat(): void
    {
        $now = 0;
        $listener = new AmqpListener(
            new ConnectionRegistry(),
            '127.0.0.1',
            0,
            heartbeatInterval: 5,
            clock: static function () use (&$now): int {
                return $now * 1_000_000_000;
            }
        );
        $listener->start();

        try {
            $client = $this->connect($listener);
            $codec = new FrameCodec();
            $this->connectAmqp($listener, $client, 5);

            $now = 4;
            fwrite($client, $codec->encode(Frame::methodFrame(1, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));

            $now = 8;
            $listener->tick();
            self::assertSame('', fread($client, 8192));

            $now = 9;
            self::assertSame(Frame::TYPE_HEARTBEAT, $this->readFrame($listener, $client)->type);
            fclose($client);
        } finally {
            $listener->stop();
        }
    }

    public function testOneStaleConnectionClosesWhileAnotherActiveConnectionSurvives(): void
    {
        $now = 0;
        $connections = new ConnectionRegistry();
        $listener = new AmqpListener(
            $connections,
            '127.0.0.1',
            0,
            heartbeatInterval: 5,
            clock: static function () use (&$now): int {
                return $now * 1_000_000_000;
            }
        );
        $listener->start();

        try {
            $staleClient = $this->connect($listener);
            $activeClient = $this->connect($listener);
            $this->connectAmqp($listener, $staleClient, 5);
            $this->connectAmqp($listener, $activeClient, 5);
            self::assertSame(2, $connections->count());

            $now = 9;
            fwrite($activeClient, (new FrameCodec())->encode(Frame::heartbeatFrame()));
            $listener->tick();
            self::assertSame(2, $connections->count());

            $now = 10;
            $listener->tick();

            self::assertSame(1, $connections->count());
            self::assertFalse(feof($activeClient));
            fclose($staleClient);
            fclose($activeClient);
        } finally {
            $listener->stop();
        }
    }

    public function testListenerStopClosesPort(): void
    {
        $listener = new AmqpListener(new ConnectionRegistry(), '127.0.0.1', 0);
        $listener->start();
        $port = $listener->port();

        $listener->stop();

        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $port), $errorCode, $errorMessage, 0.1);
        if (is_resource($client)) {
            fclose($client);
        }

        self::assertFalse($client);
    }

    /**
     * @return resource
     */
    private function connect(AmqpListener $listener): mixed
    {
        $client = @stream_socket_client(
            sprintf('tcp://127.0.0.1:%d', $listener->port()),
            $errorCode,
            $errorMessage,
            1.0
        );
        self::assertIsResource($client, sprintf('Could not connect to AMQP listener: %s', $errorMessage));
        stream_set_blocking($client, false);

        return $client;
    }

    /**
     * @param resource $client
     * @return array{0: int, 1: int}
     */
    private function readMethod(AmqpListener $listener, mixed $client): array
    {
        return $this->readFrame($listener, $client)->method();
    }

    /**
     * @param resource $client
     */
    private function readFrame(AmqpListener $listener, mixed $client): Frame
    {
        $codec = new FrameCodec();

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $listener->tick();
            $bytes = fread($client, 8192);
            if (is_string($bytes) && $bytes !== '') {
                $frames = $codec->push($bytes);
                if ($frames !== []) {
                    return $frames[0];
                }
            }
            usleep(1000);
        }

        self::fail('Timed out waiting for AMQP frame.');
    }

    /**
     * @param resource $client
     */
    private function connectAmqp(AmqpListener $listener, mixed $client, int $heartbeat): void
    {
        $codec = new FrameCodec();
        fwrite($client, AmqpConnection::PROTOCOL_HEADER);
        self::assertSame([10, 10], $this->readMethod($listener, $client));
        fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 11)));
        self::assertSame([10, 30], $this->readMethod($listener, $client));
        fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 31, $this->tuneOk($heartbeat))));
        $listener->tick();
        fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 40)));
        self::assertSame([10, 41], $this->readMethod($listener, $client));
    }

    private function tuneOk(int $heartbeat): string
    {
        return pack('nNn', 0, 131072, $heartbeat);
    }
}
