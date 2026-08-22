<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Broker\DeliveryState;
use Flux\Broker\DestinationType;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\MessageRepository;
use Flux\Persistence\Postgres\MessageRouteRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use Flux\Runtime\RuntimeDiagnostics;
use RuntimeException;
use Throwable;

final readonly class BrokerStatsCommand
{
    public function __construct(
        private ReadOnlyDatabaseContext $context,
        private RuntimeDiagnostics $diagnostics
    ) {
    }

    /**
     * @param resource $output
     */
    public function run(mixed $output): int
    {
        try {
            $runtime = $this->diagnostics->stats();
            $runtimeAvailable = true;
        } catch (RuntimeException) {
            $runtime = [];
            $runtimeAvailable = false;
        }

        try {
            $connection = $this->context->connect();
            $virtualHosts = (new VirtualHostRepository($connection))->countAll();
            $queues = (new DestinationRepository($connection))->countByType(DestinationType::Queue);
            $messages = (new MessageRepository($connection))->countAll();
            $routes = (new MessageRouteRepository($connection))->countAll();
            $deliveries = (new DeliveryRepository($connection))->countByState();
        } catch (Throwable $exception) {
            $this->write($output, sprintf("ERROR: %s\n", $this->context->safeError($exception)));

            return 1;
        }

        $this->write($output, "Broker Statistics\n\n");
        $this->write($output, "Runtime:\n");
        if ($runtimeAvailable) {
            $limits = is_array($runtime['limits'] ?? null) ? $runtime['limits'] : [];
            $this->write($output, sprintf(
                "  Connections: %d / %s\n",
                (int) ($runtime['connections'] ?? 0),
                self::limit((int) ($limits['max_connections'] ?? 0))
            ));
            $this->write($output, sprintf("  Consumers:   %d\n\n", (int) ($runtime['consumers'] ?? 0)));
        } else {
            $this->write($output, "  Runtime: unavailable\n\n");
        }

        $this->write($output, "Persistence:\n");
        $this->write($output, sprintf("  Virtual Hosts: %d\n", $virtualHosts));
        $this->write($output, sprintf("  Queues:        %d\n", $queues));
        $this->write($output, sprintf("  Messages:      %d\n", $messages));
        $this->write($output, sprintf("  Routes:        %d\n", $routes));
        $this->write($output, "  Deliveries:\n");
        $this->write($output, sprintf("    Pending:       %d\n", self::deliveryCount($deliveries, DeliveryState::Pending)));
        $this->write($output, sprintf("    Reserved:      %d\n", self::deliveryCount($deliveries, DeliveryState::Reserved)));
        $this->write($output, sprintf("    Acknowledged:  %d\n", self::deliveryCount($deliveries, DeliveryState::Acknowledged)));
        $this->write($output, sprintf("    Rejected:      %d\n", self::deliveryCount($deliveries, DeliveryState::Rejected)));

        return 0;
    }

    /**
     * @param array<string, int> $counts
     */
    private static function deliveryCount(array $counts, DeliveryState $state): int
    {
        return $counts[$state->value] ?? 0;
    }

    private static function limit(int $limit): string
    {
        return $limit === 0 ? 'unlimited' : (string) $limit;
    }

    /**
     * @param resource $output
     */
    private function write(mixed $output, string $message): void
    {
        fwrite($output, $message);
    }
}
