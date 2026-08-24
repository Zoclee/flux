# Graph Report - flux  (2026-08-24)

## Corpus Check
- 165 files · ~65,928 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1729 nodes · 5451 edges · 89 communities (50 shown, 39 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `e74322eb`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- AmqpPublishConsumeTest
- Frame
- AmqpTopologyTest
- Application
- AmqpListener
- Flux\Runtime\RuntimeDiagnostics
- BindingRepository
- Connection
- Application.php
- DeliveryRepository
- SchemaTest
- Migrator
- Broker
- PublishTransactionTest
- BrokerTopologyManagementTest
- DeliveryRepositoryTest
- UserRepository
- Table
- composer.json
- BrokerDeliveryTest
- AmqpMethodReader
- BrokerRuntime
- AdminCommandTest
- virtual_hosts
- AuthenticatedUser
- RuntimeException
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- ResourceLimits
- SubscriptionRepository
- AmqpConnectionTest.php
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServerTest
- PublishTransaction
- InvalidArgumentException
- Uuid
- ReadOnlyDatabaseContext
- ConnectionTest
- RuntimeConnection
- ConnectionConfig
- Broker.php
- Dotenv
- DestinationRepositoryTest
- DateTimeImmutable
- BrokerRuntimeIntegrationTest
- DestinationRepository
- MessageRepository
- ResourcePermissionMatcher
- DiagnosticsCommandTest
- BrokerStatsCommand
- TopicMatcher
- Flux
- QueueShowCommand
- ReadinessCommandTest
- Flux\Broker\DeliveryState
- PublishRequestTest
- PublishResult
- ReadinessCommand
- ConnectionRegistryTest
- DeliveryStateException
- AvailableRuntimeDiagnostics
- VirtualHost
- MessagePeekCommand
- ConsumerRegistryTest
- [0.1.0-RC1] - 24 Aug 2026
- UnavailableRuntimeDiagnostics
- AuthorizationResult
- UserPermissions
- ReadyRuntimeDiagnostics
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
- `BrokerTopologyManagementTest` --references--> `BindingRepository`  [EXTRACTED]
  tests/Integration/Broker/BrokerTopologyManagementTest.php → src/Persistence/Postgres/BindingRepository.php

## Import Cycles
- None detected.

## Communities (89 total, 39 thin omitted)

### Community 1 - "Frame"
Cohesion: 0.05
Nodes (8): AmqpConnectionState, TopologyException, AmqpConnection, AuthorizationPermission, Frame, self, AmqpConnectionTest, FrameCodecTest

### Community 3 - "Application"
Cohesion: 0.12
Nodes (4): RuntimeDiagnostics, Application, RuntimeDiagnosticsClient, ApplicationTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.14
Nodes (5): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, FakeRuntimeDiagnostics

### Community 6 - "BindingRepository"
Cohesion: 0.06
Nodes (4): Binding, BindingRepository, BrokerPublishTest, BindingRepositoryTest

### Community 7 - "Connection"
Cohesion: 0.19
Nodes (9): DateTimeZone, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, PublishRequest, VhostCreateCommand, Connection (+1 more)

### Community 8 - "Application.php"
Cohesion: 0.07
Nodes (9): MigrateCommand, UserClearPermissionsCommand, UserGrantVhostCommand, UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, UserSetPermissionsCommand, MigrationFailure (+1 more)

### Community 9 - "DeliveryRepository"
Cohesion: 0.21
Nodes (5): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO

### Community 10 - "SchemaTest"
Cohesion: 0.13
Nodes (3): MigrationResult, PDO, SchemaTest

### Community 11 - "Migrator"
Cohesion: 0.18
Nodes (3): SplFileInfo, Migrator, MigrationFilenameTest

### Community 12 - "Broker"
Cohesion: 0.06
Nodes (14): Broker, RoutingSourceType, DestinationNotFoundException, self, QueueStatus, RoutingSourceType, RoutingSource, self (+6 more)

### Community 16 - "UserRepository"
Cohesion: 0.11
Nodes (6): AuthenticationService, AuthorizationService, Authenticator, Authorizer, User, UserRepository

### Community 17 - "Table"
Cohesion: 0.19
Nodes (3): BindingListCommand, SubscriptionListCommand, Table

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 21 - "BrokerRuntime"
Cohesion: 0.11
Nodes (4): RuntimeState, BrokerRuntime, BrokerRuntimeTest, RecordingDrainingComponent

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AuthenticatedUser"
Cohesion: 0.15
Nodes (7): AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), ListenerAuthenticationService, InMemoryAuthenticationService

### Community 25 - "RuntimeException"
Cohesion: 0.07
Nodes (8): Flux\Broker\RoutingSourceType, JsonException, RuntimeException, ResourceLimitException, AmqpTlsConfig, RuntimeRegistrationException, TlsCertificate, ConnectionConfigTest

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 30 - "ResourceLimits"
Cohesion: 0.05
Nodes (15): Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationPermission, Flux\Broker\AuthorizationService, Flux\Runtime\RuntimeComponent, Flux\Runtime\RuntimeDrainingComponent, Flux\Runtime\RuntimeState, RuntimeComponent, self (+7 more)

### Community 32 - "AmqpConnectionTest.php"
Cohesion: 0.20
Nodes (3): Flux\Protocol\Amqp\AmqpConnectionState, FrameCodec, ProtocolException

### Community 36 - "PublishTransaction"
Cohesion: 0.26
Nodes (4): Closure, PDO, RoutingSourceType, PublishTransaction

### Community 37 - "InvalidArgumentException"
Cohesion: 0.11
Nodes (5): InvalidArgumentException, AcknowledgeRequest, RejectRequest, ReserveRequest, DeliveryRequestTest

### Community 38 - "Uuid"
Cohesion: 0.16
Nodes (4): self, self, Uuid, UuidTest

### Community 39 - "ReadOnlyDatabaseContext"
Cohesion: 0.21
Nodes (3): QueueListCommand, ReadOnlyDatabaseContext, VhostListCommand

### Community 41 - "RuntimeConnection"
Cohesion: 0.20
Nodes (3): RuntimeConnection, RuntimeConsumer, RuntimeModelTest

### Community 42 - "ConnectionConfig"
Cohesion: 0.10
Nodes (5): DbStatusCommand, ServerStartCommand, self, ConnectionConfig, self

### Community 43 - "Broker.php"
Cohesion: 0.33
Nodes (3): Closure, Closure, UserCreateCommand

### Community 46 - "DateTimeImmutable"
Cohesion: 0.16
Nodes (4): DateTimeImmutable, MessageRoute, ReleaseRequest, MessageRouteRepository

### Community 48 - "DestinationRepository"
Cohesion: 0.15
Nodes (5): Flux\Broker\DestinationType, Destination, DestinationType, DestinationRepository, DestinationType

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 57 - "Flux\Broker\DeliveryState"
Cohesion: 0.29
Nodes (3): Flux\Broker\DeliveryState, self, RetryPolicy

### Community 68 - "[0.1.0-RC1] - 24 Aug 2026"
Cohesion: 0.29
Nodes (6): [0.1.0-RC1] - 24 Aug 2026, [0.1.0-RC2] - 24 Aug 2026, Added, Changelog, Fixed, Known Limitations

### Community 70 - "AuthorizationResult"
Cohesion: 0.20
Nodes (5): AuthorizationResult, self, authorize(), AuthorizationPermission, AuthorizationPermission

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

## Knowledge Gaps
- **50 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+45 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **39 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `Application`, `BindingRepository`, `Application.php`, `DeliveryRepository`, `SchemaTest`, `Migrator`, `Broker`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `ResourceLimits`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `PublishTransaction`, `ReadOnlyDatabaseContext`, `ConnectionTest`, `ConnectionConfig`, `Broker.php`, `DestinationRepositoryTest`, `DateTimeImmutable`, `BrokerRuntimeIntegrationTest`, `DestinationRepository`, `MessageRepository`, `ReadinessCommandTest`, `ReadinessCommand`, `VirtualHost`?**
  _High betweenness centrality (0.171) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `UserRepository`, `RuntimeException`, `Connection`?**
  _High betweenness centrality (0.145) - this node is a cross-community bridge._
- **Why does `Frame` connect `Frame` to `AmqpConnectionTest.php`, `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `AmqpListener`, `InvalidArgumentException`, `Connection`, `UserRepository`, `RuntimeException`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _50 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08084859052600989 - nodes in this community are weakly interconnected._
- **Should `Frame` be split into smaller, more focused modules?**
  _Cohesion score 0.051094890510948905 - nodes in this community are weakly interconnected._
- **Should `AmqpTopologyTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14350282485875707 - nodes in this community are weakly interconnected._