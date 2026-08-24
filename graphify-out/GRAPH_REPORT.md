# Graph Report - flux  (2026-08-24)

## Corpus Check
- 165 files · ~65,633 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1721 nodes · 5435 edges · 91 communities (45 shown, 46 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `62137849`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- AmqpPublishConsumeTest
- AmqpConnection
- AmqpTopologyTest
- ConnectionConfig
- AmqpListener
- Flux\Runtime\RuntimeDiagnostics
- BindingRepository
- Connection
- Throwable
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
- DateTimeImmutable
- Uuid
- BrokerRuntime
- ConnectionTest
- RuntimeConnection
- Application.php
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
- RuntimeDiagnosticsServerTest
- PublishRequestTest
- ReadinessCommand
- RecordingRuntimeComponent
- ConnectionRegistryTest
- ServerStartCommand
- UserCreateCommand
- MessagePeekCommand
- ConsumerRegistryTest
- [0.1.0-RC1] - 24 Aug 2026
- FakeRuntimeDiagnostics
- ConnectionConfigTest
- UnavailableRuntimeDiagnostics
- RuntimeDiagnosticsClient
- ConnectionRegistry
- Flux MVP Smoke Test
- AmqpConnection.php
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

## Communities (91 total, 46 thin omitted)

### Community 1 - "AmqpConnection"
Cohesion: 0.11
Nodes (3): AmqpConnectionState, Flux\Broker\AuthorizationService, AmqpConnection

### Community 3 - "ConnectionConfig"
Cohesion: 0.07
Nodes (8): Application, DbStatusCommand, MigrateCommand, self, ConnectionConfig, self, BrokerRuntimeIntegrationTest, ApplicationTest

### Community 4 - "AmqpListener"
Cohesion: 0.07
Nodes (4): AmqpListener, AmqpTlsConfig, TlsCertificate, AmqpListenerTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.12
Nodes (6): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, AvailableRuntimeDiagnostics, ReadyRuntimeDiagnostics

### Community 7 - "Connection"
Cohesion: 0.13
Nodes (12): DateTimeZone, Flux\Broker\DeliveryState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, UserSetPermissionsCommand, VhostCreateCommand (+4 more)

### Community 8 - "Throwable"
Cohesion: 0.09
Nodes (7): UserGrantVhostCommand, UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, Table, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.17
Nodes (7): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO, DeliveryStateException, self

### Community 10 - "SchemaTest"
Cohesion: 0.10
Nodes (4): SplFileInfo, MigrationResult, PDO, SchemaTest

### Community 12 - "Broker"
Cohesion: 0.11
Nodes (7): Broker, RoutingSourceType, PublishRequest, RoutingSourceType, RoutingSource, self, VirtualHostNotFoundException

### Community 16 - "UserRepository"
Cohesion: 0.09
Nodes (6): AuthenticationService, AuthorizationService, Authenticator, Authorizer, UserClearPermissionsCommand, UserRepository

### Community 17 - "Frame"
Cohesion: 0.11
Nodes (3): Frame, self, FrameCodecTest

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AuthenticatedUser"
Cohesion: 0.09
Nodes (11): Flux\Broker\AuthenticationService, Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), FrameCodec (+3 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 32 - "RoutingSourceRepository"
Cohesion: 0.10
Nodes (5): Closure, Closure, RoutingSourceType, RoutingSourceRepository, ExclusiveQueueRegistry

### Community 36 - "ResourceLimits"
Cohesion: 0.09
Nodes (9): Flux\Broker\RoutingSourceType, PublishResult, ResourceLimitException, self, ResourceLimits, PDO, RoutingSourceType, PublishTransaction (+1 more)

### Community 37 - "DateTimeImmutable"
Cohesion: 0.06
Nodes (17): DateTimeImmutable, Flux\Runtime\RuntimeDrainingComponent, Flux\Runtime\RuntimeState, InvalidArgumentException, JsonException, RuntimeException, DestinationNotFoundException, self (+9 more)

### Community 38 - "Uuid"
Cohesion: 0.17
Nodes (3): self, Uuid, UuidTest

### Community 41 - "RuntimeConnection"
Cohesion: 0.22
Nodes (3): self, RuntimeConnection, RuntimeModelTest

### Community 42 - "Application.php"
Cohesion: 0.13
Nodes (5): BindingListCommand, QueueListCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, VhostListCommand

### Community 46 - "MessageRouteRepository"
Cohesion: 0.15
Nodes (3): self, RetryPolicy, MessageRouteRepository

### Community 48 - "DestinationRepository"
Cohesion: 0.13
Nodes (6): Flux\Broker\DestinationType, Destination, DestinationType, QueueStatus, DestinationRepository, DestinationType

### Community 50 - "ResourcePermissionMatcher"
Cohesion: 0.12
Nodes (7): AuthorizationResult, self, authorize(), AuthorizationPermission, AuthorizationPermission, ResourcePermissionMatcher, ResourcePermissionMatcherTest

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 68 - "[0.1.0-RC1] - 24 Aug 2026"
Cohesion: 0.40
Nodes (4): [0.1.0-RC1] - 24 Aug 2026, Added, Changelog, Known Limitations

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

### Community 82 - "AmqpConnection.php"
Cohesion: 0.12
Nodes (5): Flux\Broker\AuthorizationPermission, AcknowledgeRequest, RejectRequest, ReserveRequest, DeliveryRequestTest

## Knowledge Gaps
- **49 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+44 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `ConnectionConfig`, `BindingRepository`, `Throwable`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `SubscriptionRepository`, `RoutingSourceRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ResourceLimits`, `DateTimeImmutable`, `ConnectionTest`, `Application.php`, `BrokerPublishTest`, `DestinationRepositoryTest`, `MessageRouteRepository`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `ReadinessCommandTest`, `ReadinessCommand`, `ServerStartCommand`, `UserCreateCommand`?**
  _High betweenness centrality (0.170) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `AmqpListener`, `Connection`?**
  _High betweenness centrality (0.146) - this node is a cross-community bridge._
- **Why does `Broker` connect `Broker` to `AmqpPublishConsumeTest`, `AmqpConnection`, `AmqpTopologyTest`, `AmqpListener`, `BindingRepository`, `Connection`, `DeliveryRepository`, `BrokerTopologyManagementTest`, `UserRepository`, `BrokerDeliveryTest`, `BrokerRuntimeTest`, `.writeFrame`, `SubscriptionRepository`, `RoutingSourceRepository`, `ResourceLimits`, `DateTimeImmutable`, `BrokerRuntime`, `BrokerPublishTest`, `MessageRouteRepository`, `DestinationRepository`, `MessageRepository`, `ServerStartCommand`, `ConnectionRegistry`, `AmqpConnection.php`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _49 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08084859052600989 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.10931174089068826 - nodes in this community are weakly interconnected._
- **Should `AmqpTopologyTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14350282485875707 - nodes in this community are weakly interconnected._