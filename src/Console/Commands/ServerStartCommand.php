<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Broker\Broker;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\ConnectionConfig;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\MessageRepository;
use Flux\Persistence\Postgres\MessageRouteRepository;
use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\BindingRepository;
use Flux\Persistence\Postgres\RoutingSourceRepository;
use Flux\Persistence\Postgres\SubscriptionRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use Flux\Protocol\Amqp\AmqpListener;
use Flux\Runtime\BrokerRuntime;
use Flux\Runtime\ConnectionRegistry;
use Flux\Runtime\ConsumerRegistry;
use Flux\Runtime\RuntimeDiagnosticsServer;
use Throwable;

final class ServerStartCommand
{
    /**
     * @var null|callable(Broker, ConnectionRegistry, ConsumerRegistry): BrokerRuntime
     */
    private $runtimeFactory;
    private string $amqpHost;
    private int $amqpPort;
    private int $amqpHeartbeat;
    private string $diagnosticsHost;
    private int $diagnosticsPort;

    /**
     * @param null|callable(Broker, ConnectionRegistry, ConsumerRegistry): BrokerRuntime $runtimeFactory
     */
    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly string $version,
        string|callable $amqpHost = '127.0.0.1',
        int $amqpPort = 5672,
        int $amqpHeartbeat = 60,
        string $diagnosticsHost = '127.0.0.1',
        int $diagnosticsPort = 5673,
        ?callable $runtimeFactory = null
    ) {
        if (is_callable($amqpHost)) {
            $runtimeFactory = $amqpHost;
            $amqpHost = '127.0.0.1';
            $amqpPort = 5672;
            $amqpHeartbeat = 60;
            $diagnosticsHost = '127.0.0.1';
            $diagnosticsPort = 5673;
        }

        $this->amqpHost = $amqpHost;
        $this->amqpPort = $amqpPort;
        $this->amqpHeartbeat = $amqpHeartbeat;
        $this->diagnosticsHost = $diagnosticsHost;
        $this->diagnosticsPort = $diagnosticsPort;
        $this->runtimeFactory = $runtimeFactory;
    }

    /**
     * @param resource $output
     */
    public function run(mixed $output): int
    {
        $this->write($output, "Flux Message Broker\n\n");
        $this->write($output, sprintf("Version:  %s\n", $this->version));
        $this->write($output, "Status:   starting\n");

        try {
            $connection = Connection::fromConfig($this->config);
            $connection->pdo();

            $broker = new Broker(
                new VirtualHostRepository($connection),
                new PublishTransaction($connection),
                new DestinationRepository($connection),
                new SubscriptionRepository($connection),
                new DeliveryRepository($connection),
                new BindingRepository($connection),
                new RoutingSourceRepository($connection),
                new MessageRouteRepository($connection),
                new MessageRepository($connection)
            );
            $connections = new ConnectionRegistry();
            $consumers = new ConsumerRegistry();
            $runtime = $this->createRuntime($broker, $connections, $consumers);
        } catch (Throwable $exception) {
            $this->write($output, "Database: disconnected\n\n");
            $this->write($output, sprintf("ERROR: %s\n", $this->config->redact($exception->getMessage())));

            return 1;
        }

        $this->write($output, "Database: connected\n\n");
        $this->write($output, "Protocols:\n");
        $this->write($output, sprintf(
            "  AMQP 0-9-1  %s:%d  heartbeat=%d\n\n",
            $this->amqpHost,
            $this->amqpPort,
            $this->amqpHeartbeat
        ));
        $this->write($output, sprintf(
            "Diagnostics:\n  Runtime     %s:%d\n\n",
            $this->diagnosticsHost,
            $this->diagnosticsPort
        ));
        $this->write($output, "Runtime started.\n");
        $this->write($output, "Press Ctrl+C to stop.\n");

        $this->registerSignalHandlers($runtime, $output);

        try {
            $runtime->run();
        } catch (Throwable $exception) {
            $runtime->stop();
            $this->write($output, sprintf("ERROR: %s\n", $this->config->redact($exception->getMessage())));

            return 1;
        }

        $this->write($output, "Runtime stopped.\n");

        return 0;
    }

    /**
     * @param resource $output
     */
    private function registerSignalHandlers(BrokerRuntime $runtime, mixed $output): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        $handler = function () use ($runtime, $output): void {
            $this->write($output, "Shutdown requested.\n");
            $runtime->requestShutdown();
        };

        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }

    private function createRuntime(
        Broker $broker,
        ConnectionRegistry $connections,
        ConsumerRegistry $consumers
    ): BrokerRuntime {
        if ($this->runtimeFactory !== null) {
            return ($this->runtimeFactory)($broker, $connections, $consumers);
        }

        return new BrokerRuntime(
            $broker,
            $connections,
            $consumers,
            components: [
                new AmqpListener(
                    $connections,
                    $this->amqpHost,
                    $this->amqpPort,
                    broker: $broker,
                    consumers: $consumers,
                    heartbeatInterval: $this->amqpHeartbeat
                ),
                new RuntimeDiagnosticsServer($connections, $consumers, $this->diagnosticsHost, $this->diagnosticsPort),
            ]
        );
    }

    /**
     * @param resource $output
     */
    private function write(mixed $output, string $message): void
    {
        fwrite($output, $message);
    }
}
