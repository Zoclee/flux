<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Broker\Broker;
use Flux\Broker\Authenticator;
use Flux\Broker\Authorizer;
use Flux\Broker\ResourceLimits;
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
use Flux\Persistence\Postgres\UserRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use Flux\Protocol\Amqp\AmqpListener;
use Flux\Protocol\Amqp\AmqpTlsConfig;
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
    private bool $amqpEnabled;
    private string $amqpHost;
    private int $amqpPort;
    private int $amqpHeartbeat;
    private bool $amqpTlsEnabled;
    private string $amqpTlsHost;
    private int $amqpTlsPort;
    private ?string $amqpTlsCert;
    private ?string $amqpTlsKey;
    private ?string $amqpTlsCa;
    private string $diagnosticsHost;
    private int $diagnosticsPort;
    private ResourceLimits $limits;

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
        ?callable $runtimeFactory = null,
        bool $amqpEnabled = true,
        bool $amqpTlsEnabled = false,
        string $amqpTlsHost = '0.0.0.0',
        int $amqpTlsPort = 5671,
        ?string $amqpTlsCert = null,
        ?string $amqpTlsKey = null,
        ?string $amqpTlsCa = null,
        ?ResourceLimits $limits = null
    ) {
        if (is_callable($amqpHost)) {
            $runtimeFactory = $amqpHost;
            $amqpEnabled = true;
            $amqpHost = '127.0.0.1';
            $amqpPort = 5672;
            $amqpHeartbeat = 60;
            $amqpTlsEnabled = false;
            $amqpTlsHost = '0.0.0.0';
            $amqpTlsPort = 5671;
            $amqpTlsCert = null;
            $amqpTlsKey = null;
            $amqpTlsCa = null;
            $diagnosticsHost = '127.0.0.1';
            $diagnosticsPort = 5673;
            $limits = null;
        }

        $this->amqpEnabled = $amqpEnabled;
        $this->amqpHost = $amqpHost;
        $this->amqpPort = $amqpPort;
        $this->amqpHeartbeat = $amqpHeartbeat;
        $this->amqpTlsEnabled = $amqpTlsEnabled;
        $this->amqpTlsHost = $amqpTlsHost;
        $this->amqpTlsPort = $amqpTlsPort;
        $this->amqpTlsCert = $amqpTlsCert;
        $this->amqpTlsKey = $amqpTlsKey;
        $this->amqpTlsCa = $amqpTlsCa;
        $this->diagnosticsHost = $diagnosticsHost;
        $this->diagnosticsPort = $diagnosticsPort;
        $this->limits = $limits ?? new ResourceLimits();
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
            $users = new UserRepository($connection);
            $authenticator = new Authenticator($users);
            $authorizer = new Authorizer($users);
            $tlsConfig = $this->amqpTlsEnabled
                ? new AmqpTlsConfig((string) $this->amqpTlsCert, (string) $this->amqpTlsKey, $this->amqpTlsCa)
                : null;

            $broker = new Broker(
                new VirtualHostRepository($connection),
                new PublishTransaction($connection, $this->limits),
                new DestinationRepository($connection),
                new SubscriptionRepository($connection),
                new DeliveryRepository($connection),
                new BindingRepository($connection),
                new RoutingSourceRepository($connection),
                new MessageRouteRepository($connection),
                new MessageRepository($connection),
                $this->limits
            );
            $connections = new ConnectionRegistry();
            $consumers = new ConsumerRegistry();
            $runtime = $this->createRuntime($broker, $authenticator, $authorizer, $connections, $consumers, $tlsConfig);
        } catch (Throwable $exception) {
            $this->write($output, "Startup:  failed\n\n");
            $this->write($output, sprintf("ERROR: %s\n", $this->config->redact($exception->getMessage())));

            return 1;
        }

        $this->write($output, "Database: connected\n\n");
        $this->write($output, "Protocols:\n");
        if ($this->amqpEnabled) {
            $this->write($output, sprintf(
                "  AMQP 0-9-1      %s:%d  heartbeat=%d frame_max=%d message_max=%d\n",
                $this->amqpHost,
                $this->amqpPort,
                $this->amqpHeartbeat,
                $this->limits->maxFrameSize,
                $this->limits->maxMessageSize
            ));
        }

        if ($tlsConfig !== null) {
            $this->write($output, sprintf(
                "  AMQP 0-9-1 TLS  %s:%d  heartbeat=%d frame_max=%d message_max=%d\n",
                $this->amqpTlsHost,
                $this->amqpTlsPort,
                $this->amqpHeartbeat,
                $this->limits->maxFrameSize,
                $this->limits->maxMessageSize
            ));
        }

        $this->write($output, "\n");
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
        Authenticator $authenticator,
        Authorizer $authorizer,
        ConnectionRegistry $connections,
        ConsumerRegistry $consumers,
        ?AmqpTlsConfig $tlsConfig
    ): BrokerRuntime {
        if ($this->runtimeFactory !== null) {
            return ($this->runtimeFactory)($broker, $connections, $consumers);
        }

        $components = [];
        if ($this->amqpEnabled) {
            $components[] = new AmqpListener(
                $connections,
                $this->amqpHost,
                $this->amqpPort,
                maxFrameSize: $this->limits->maxFrameSize,
                broker: $broker,
                authenticator: $authenticator,
                authorizer: $authorizer,
                consumers: $consumers,
                maxMessageSize: $this->limits->maxMessageSize,
                heartbeatInterval: $this->amqpHeartbeat,
                limits: $this->limits
            );
        }

        if ($tlsConfig !== null) {
            $components[] = new AmqpListener(
                $connections,
                $this->amqpTlsHost,
                $this->amqpTlsPort,
                maxFrameSize: $this->limits->maxFrameSize,
                broker: $broker,
                authenticator: $authenticator,
                authorizer: $authorizer,
                consumers: $consumers,
                maxMessageSize: $this->limits->maxMessageSize,
                heartbeatInterval: $this->amqpHeartbeat,
                tls: $tlsConfig,
                limits: $this->limits
            );
        }

        $components[] = new RuntimeDiagnosticsServer($connections, $consumers, $this->diagnosticsHost, $this->diagnosticsPort, $this->limits);

        return new BrokerRuntime(
            $broker,
            $connections,
            $consumers,
            components: $components
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
