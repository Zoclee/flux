<?php

declare(strict_types=1);

namespace Flux\Protocol\Amqp;

use Flux\Broker\Broker;
use Flux\Broker\AuthenticationService;
use Flux\Broker\AuthorizationService;
use Flux\Runtime\ConnectionRegistry;
use Flux\Runtime\ConsumerRegistry;
use Flux\Runtime\RuntimeComponent;
use RuntimeException;

final class AmqpListener implements RuntimeComponent
{
    /**
     * @var resource|null
     */
    private mixed $server = null;

    /**
     * @var array<string, AmqpConnection>
     */
    private array $connections = [];

    public function __construct(
        private readonly ConnectionRegistry $runtimeConnections,
        private string $host = '127.0.0.1',
        private int $port = 5672,
        private readonly int $maxFrameSize = 131072,
        private readonly ?Broker $broker = null,
        private readonly ?AuthenticationService $authenticator = null,
        private readonly ?AuthorizationService $authorizer = null,
        private readonly ?ConsumerRegistry $consumers = null,
        private readonly int $heartbeatInterval = 60,
        private readonly mixed $clock = null
    ) {
        if ($this->host === '') {
            throw new RuntimeException('AMQP listener host must not be empty.');
        }

        if ($this->port < 0 || $this->port > 65535) {
            throw new RuntimeException('AMQP listener port must be between 0 and 65535.');
        }

        if ($this->heartbeatInterval < 0 || $this->heartbeatInterval > 65535) {
            throw new RuntimeException('AMQP heartbeat interval must fit in an unsigned short.');
        }
    }

    public function start(): void
    {
        if ($this->server !== null) {
            return;
        }

        $address = sprintf('tcp://%s:%d', $this->host, $this->port);
        $server = @stream_socket_server($address, $errorCode, $errorMessage);

        if ($server === false) {
            throw new RuntimeException(sprintf(
                'Could not start AMQP listener on %s:%d: %s (%d)',
                $this->host,
                $this->port,
                $errorMessage,
                $errorCode
            ));
        }

        stream_set_blocking($server, false);
        $this->server = $server;
        $localName = stream_socket_get_name($server, false);
        if (is_string($localName) && preg_match('/:(\d+)$/', $localName, $matches) === 1) {
            $this->port = (int) $matches[1];
        }
    }

    public function tick(): void
    {
        if ($this->server === null) {
            return;
        }

        while (($client = @stream_socket_accept($this->server, 0)) !== false) {
            $key = (string) (int) $client;
            $this->connections[$key] = new AmqpConnection(
                $client,
                $this->runtimeConnections,
                $this->maxFrameSize,
                $this->broker,
                $this->authenticator,
                $this->authorizer,
                $this->consumers,
                heartbeatInterval: $this->heartbeatInterval,
                clock: $this->clock
            );
        }

        foreach ($this->connections as $key => $connection) {
            $connection->tick();
            if ($connection->isClosed()) {
                unset($this->connections[$key]);
            }
        }
    }

    public function stop(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }

        $this->connections = [];

        if (is_resource($this->server)) {
            fclose($this->server);
        }

        $this->server = null;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function connectionCount(): int
    {
        return count($this->connections);
    }
}
