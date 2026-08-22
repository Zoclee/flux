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
            $codec = new FrameCodec();

            fwrite($client, AmqpConnection::PROTOCOL_HEADER);
            self::assertSame([10, 10], $this->readMethod($listener, $client));
            self::assertSame(1, $runtimeConnections->count());

            fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 11)));
            self::assertSame([10, 30], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 31)));
            $listener->tick();

            fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 40)));
            self::assertSame([10, 41], $this->readMethod($listener, $client));

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
        $codec = new FrameCodec();

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $listener->tick();
            $bytes = fread($client, 8192);
            if (is_string($bytes) && $bytes !== '') {
                $frames = $codec->push($bytes);
                if ($frames !== []) {
                    return $frames[0]->method();
                }
            }
            usleep(1000);
        }

        self::fail('Timed out waiting for AMQP method frame.');
    }
}
