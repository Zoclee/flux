# Graph Report - flux  (2026-08-24)

## Corpus Check
- 165 files · ~65,906 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1727 nodes · 5449 edges · 91 communities (44 shown, 47 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `7fbcdce4`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- AmqpPublishConsumeTest
- AmqpConnection
- AmqpTopologyTest
- Application
- AmqpListener
- Flux\Runtime\RuntimeDiagnostics
- BindingRepository
- PHPUnit\Framework\TestCase
- Table
- DeliveryRepository
- SchemaTest
- AmqpConnectionTest
- Broker
- PublishTransactionTest
- BrokerTopologyManagementTest
- DeliveryRepositoryTest
- UserRepository
- Frame
- composer.json
- BrokerDeliveryTest
- AmqpMethodReader
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- AuthenticatedUser
- .writeFrame
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- ConsumerRegistry
- SubscriptionRepository
- RoutingSourceRepository
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServer
- ResourceLimits
- RuntimeException
- Uuid
- Connection
- ConnectionTest
- DateTimeImmutable
- ConnectionConfig
- BrokerPublishTest
- Dotenv
- DestinationRepositoryTest
- MessageRouteRepository
- BindingRepositoryTest
- DestinationRepository
- MessageRepository
- ResourcePermissionMatcher
- DiagnosticsCommandTest
- BrokerStatsCommand
- TopicMatcher
- Flux
- QueueShowCommand
- ReadinessCommandTest
- ApplicationTest
- PublishRequestTest
- VirtualHostNotFoundException
- RecordingRuntimeComponent
- ConnectionRegistryTest
- RoutingSource
- Destination
- VirtualHost
- MessagePeekCommand
- ConsumerRegistryTest
- [0.1.0-RC1] - 24 Aug 2026
- ExclusiveQueueRegistry
- AuthorizationResult
- UserPermissions
- RuntimeDiagnosticsClient
- Authorizer
- Authenticator
- Flux MVP Smoke Test
- 20260820_120000_create_schema_migrations.sql

## God Nodes (most connected - your core abstractions)
1. `AmqpPublishConsumeTest` - 190 edges
2. `Frame` - 161 edges
3. `Connection` - 137 edges
4. `AmqpConnection` - 116 edges
5. `Broker` - 74 edges
6. `AmqpTopologyTest` - 63 edges
7. `AmqpListener` - 54 edges
8. `DestinationRepository` - 49 edges
9. `ConnectionConfig` - 48 edges
10. `DeliveryRepository` - 45 edges

## Surprising Connections (you probably didn't know these)
- `BrokerDeliveryTest` --references--> `Broker`  [EXTRACTED]
  tests/Integration/Broker/BrokerDeliveryTest.php → src/Broker/Broker.php
- `BrokerPublishTest` --references--> `Broker`  [EXTRACTED]
  tests/Integration/Broker/BrokerPublishTest.php → src/Broker/Broker.php
- `BrokerTopologyManagementTest` --references--> `Broker`  [EXTRACTED]
  tests/Integration/Broker/BrokerTopologyManagementTest.php → src/Broker/Broker.php
- `AdminCommandTest` --references--> `ReadOnlyDatabaseContext`  [EXTRACTED]
  tests/Integration/Postgres/AdminCommandTest.php → src/Console/Commands/ReadOnlyDatabaseContext.php
- `BrokerPublishTest` --references--> `BindingRepository`  [EXTRACTED]
  tests/Integration/Broker/BrokerPublishTest.php → src/Persistence/Postgres/BindingRepository.php

## Import Cycles
- None detected.

## Communities (91 total, 47 thin omitted)

### Community 4 - "AmqpListener"
Cohesion: 0.06
Nodes (5): AmqpListener, AmqpTlsConfig, TlsCertificate, AmqpListenerTest, BrokerRuntimeIntegrationTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.07
Nodes (9): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, ReadinessCommand, AvailableRuntimeDiagnostics, UnavailableRuntimeDiagnostics, ReadyRuntimeDiagnostics (+1 more)

### Community 7 - "PHPUnit\Framework\TestCase"
Cohesion: 0.13
Nodes (10): DateTimeZone, Flux\Broker\DeliveryState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, SplFileInfo, Migrator (+2 more)

### Community 8 - "Table"
Cohesion: 0.11
Nodes (5): UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, VhostListCommand, Table

### Community 9 - "DeliveryRepository"
Cohesion: 0.22
Nodes (5): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO

### Community 10 - "SchemaTest"
Cohesion: 0.13
Nodes (3): MigrationResult, PDO, SchemaTest

### Community 17 - "Frame"
Cohesion: 0.10
Nodes (4): AmqpConnectionState, Frame, self, FrameCodecTest

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 21 - "BrokerRuntimeTest"
Cohesion: 0.15
Nodes (3): Flux\Runtime\RuntimeDrainingComponent, BrokerRuntimeTest, RecordingDrainingComponent

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AuthenticatedUser"
Cohesion: 0.08
Nodes (12): Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationService, Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost() (+4 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 30 - "ConsumerRegistry"
Cohesion: 0.08
Nodes (8): Closure, RuntimeState, ServerStartCommand, Closure, UserCreateCommand, BrokerRuntime, Closure, ConsumerRegistry

### Community 35 - "RuntimeDiagnosticsServer"
Cohesion: 0.16
Nodes (3): RuntimeComponent, RuntimeDiagnosticsServer, RuntimeDiagnosticsServerTest

### Community 36 - "ResourceLimits"
Cohesion: 0.08
Nodes (10): Flux\Broker\RoutingSourceType, Closure, PublishResult, ResourceLimitException, self, ResourceLimits, PDO, RoutingSourceType (+2 more)

### Community 37 - "RuntimeException"
Cohesion: 0.06
Nodes (14): InvalidArgumentException, JsonException, RuntimeException, AcknowledgeRequest, DestinationNotFoundException, self, RejectRequest, ReserveRequest (+6 more)

### Community 38 - "Uuid"
Cohesion: 0.12
Nodes (5): PublishRequest, self, self, Uuid, UuidTest

### Community 39 - "Connection"
Cohesion: 0.08
Nodes (6): UserClearPermissionsCommand, UserGrantVhostCommand, UserSetPermissionsCommand, VhostCreateCommand, Connection, self

### Community 41 - "DateTimeImmutable"
Cohesion: 0.09
Nodes (9): DateTimeImmutable, Flux\Broker\AuthorizationPermission, Flux\Runtime\RuntimeState, ReleaseRequest, ConnectionRegistry, RuntimeConnection, RuntimeConsumer, RuntimeRegistrationException (+1 more)

### Community 42 - "ConnectionConfig"
Cohesion: 0.08
Nodes (8): BindingListCommand, DbStatusCommand, MigrateCommand, QueueListCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, ConnectionConfig, self

### Community 48 - "DestinationRepository"
Cohesion: 0.11
Nodes (7): Flux\Broker\DestinationType, self, RetryPolicy, DestinationRepository, DestinationType, MigrationFailure, Throwable

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 62 - "RoutingSource"
Cohesion: 0.40
Nodes (3): RoutingSourceType, RoutingSourceType, RoutingSource

### Community 63 - "Destination"
Cohesion: 0.27
Nodes (3): Destination, DestinationType, QueueStatus

### Community 68 - "[0.1.0-RC1] - 24 Aug 2026"
Cohesion: 0.40
Nodes (4): [0.1.0-RC1] - 24 Aug 2026, Added, Changelog, Known Limitations

### Community 70 - "AuthorizationResult"
Cohesion: 0.25
Nodes (4): AuthorizationResult, self, authorize(), AuthorizationPermission

### Community 73 - "Authorizer"
Cohesion: 0.33
Nodes (3): AuthorizationService, Authorizer, AuthorizationPermission

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

## Knowledge Gaps
- **49 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+44 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **47 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `Application`, `AmqpListener`, `Flux\Runtime\RuntimeDiagnostics`, `BindingRepository`, `PHPUnit\Framework\TestCase`, `Table`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `ConsumerRegistry`, `SubscriptionRepository`, `RoutingSourceRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ResourceLimits`, `ConnectionTest`, `DateTimeImmutable`, `ConnectionConfig`, `BrokerPublishTest`, `DestinationRepositoryTest`, `MessageRouteRepository`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `ReadinessCommandTest`, `VirtualHost`?**
  _High betweenness centrality (0.171) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `AmqpListener`, `Connection`, `PHPUnit\Framework\TestCase`?**
  _High betweenness centrality (0.145) - this node is a cross-community bridge._
- **Why does `Broker` connect `Broker` to `AmqpPublishConsumeTest`, `AmqpConnection`, `AmqpTopologyTest`, `AmqpListener`, `BindingRepository`, `PHPUnit\Framework\TestCase`, `DeliveryRepository`, `BrokerTopologyManagementTest`, `BrokerDeliveryTest`, `BrokerRuntimeTest`, `AuthenticatedUser`, `.writeFrame`, `ConsumerRegistry`, `SubscriptionRepository`, `RoutingSourceRepository`, `ResourceLimits`, `RuntimeException`, `Uuid`, `DateTimeImmutable`, `BrokerPublishTest`, `MessageRouteRepository`, `DestinationRepository`, `MessageRepository`, `VirtualHostNotFoundException`, `RoutingSource`, `Destination`, `ExclusiveQueueRegistry`?**
  _High betweenness centrality (0.066) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _49 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08084859052600989 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.11428571428571428 - nodes in this community are weakly interconnected._
- **Should `AmqpTopologyTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14350282485875707 - nodes in this community are weakly interconnected._