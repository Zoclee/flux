# Graph Report - flux  (2026-08-23)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 1633 nodes · 5067 edges · 91 communities (38 shown, 53 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 104 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `fa6fc752`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- AmqpPublishConsumeTest
- AmqpConnection
- Frame
- ConnectionConfig
- AmqpListener
- RuntimeException
- BindingRepository
- Connection
- Application.php
- DeliveryRepository
- SchemaTest
- AmqpConnectionTest
- Broker
- PublishTransactionTest
- BrokerTopologyManagementTest
- DeliveryRepositoryTest
- UserRepository
- DateTimeImmutable
- composer.json
- BrokerDeliveryTest
- DestinationRepository
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- AuthenticatedUser
- Migrator
- AcknowledgeRequest
- VirtualHostRepository
- UserRepositoryTest
- SubscriptionRepositoryTest
- AmqpMethodReader
- MessageRepository
- SubscriptionRepository
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServer
- ResourceLimits
- MessageRouteRepository
- Uuid
- BrokerRuntimeIntegrationTest
- ConnectionTest
- BrokerRuntime
- ReadOnlyDatabaseContext
- RoutingSourceRepository
- DotenvTest
- DestinationRepositoryTest
- AuthorizationResult
- PublishTransaction
- RuntimeConnection
- ConsumerRegistry
- RoutingSource
- ResourcePermissionMatcher
- ExclusiveQueueRegistry
- TopicMatcher
- DiagnosticsCommandTest
- BrokerStatsCommand
- ConnectionRegistry
- ReadinessCommandTest
- PublishRequestTest
- RuntimeDiagnosticsServerTest
- UserPermissions
- Table
- QueueShowCommand
- PublishResult
- MessagePeekCommand
- .createRuntime
- UserCreateCommand
- ConsumerRegistryTest
- QueueListCommand
- ApplicationTest
- ConnectionRegistryTest
- ConsumerListCommand
- UserClearPermissionsCommand
- UserGrantVhostCommand
- UserListCommand
- UserListPermissionsCommand
- UserListVhostsCommand
- UserSetPermissionsCommand
- VhostCreateCommand
- VhostListCommand
- 20260820_120000_create_schema_migrations.sql

## God Nodes (most connected - your core abstractions)
1. `AmqpPublishConsumeTest` - 163 edges
2. `Frame` - 151 edges
3. `Connection` - 131 edges
4. `AmqpConnection` - 114 edges
5. `Broker` - 73 edges
6. `AmqpTopologyTest` - 62 edges
7. `AmqpListener` - 50 edges
8. `ConnectionConfig` - 44 edges
9. `PublishTransactionTest` - 38 edges
10. `DestinationRepository` - 38 edges

## Surprising Connections (you probably didn't know these)
- `AmqpPublishConsumeTest` --references--> `Connection`  [EXTRACTED]
  tests/Integration/Protocol/Amqp/AmqpPublishConsumeTest.php → src/Persistence/Postgres/Connection.php
- `SchemaTest` --references--> `Connection`  [EXTRACTED]
  tests/Integration/Postgres/SchemaTest.php → src/Persistence/Postgres/Connection.php
- `BrokerDeliveryTest` --references--> `Broker`  [EXTRACTED]
  tests/Integration/Broker/BrokerDeliveryTest.php → src/Broker/Broker.php
- `BrokerPublishTest` --references--> `Broker`  [EXTRACTED]
  tests/Integration/Broker/BrokerPublishTest.php → src/Broker/Broker.php
- `BrokerTopologyManagementTest` --references--> `Broker`  [EXTRACTED]
  tests/Integration/Broker/BrokerTopologyManagementTest.php → src/Broker/Broker.php

## Import Cycles
- None detected.

## Communities (91 total, 53 thin omitted)

### Community 1 - "AmqpConnection"
Cohesion: 0.06
Nodes (9): AmqpConnectionState, Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationPermission, Flux\Broker\AuthorizationService, Flux\Runtime\RuntimeComponent, TopologyException, AmqpConnection, AuthorizationPermission (+1 more)

### Community 2 - "Frame"
Cohesion: 0.09
Nodes (5): Frame, self, FrameCodec, AmqpTopologyTest, FrameCodecTest

### Community 3 - "ConnectionConfig"
Cohesion: 0.09
Nodes (8): RuntimeDiagnostics, Application, DbStatusCommand, MigrateCommand, self, ConnectionConfig, self, RuntimeDiagnosticsClient

### Community 4 - "AmqpListener"
Cohesion: 0.09
Nodes (3): AmqpListener, AmqpTlsConfig, AmqpListenerTest

### Community 5 - "RuntimeException"
Cohesion: 0.05
Nodes (14): Flux\Runtime\RuntimeDiagnostics, RuntimeException, DestinationNotFoundException, self, self, SubscriptionNotFoundException, HealthCommand, ReadinessCommand (+6 more)

### Community 6 - "BindingRepository"
Cohesion: 0.06
Nodes (4): Binding, BindingRepository, BrokerPublishTest, BindingRepositoryTest

### Community 7 - "Connection"
Cohesion: 0.15
Nodes (6): Flux\Broker\RoutingSourceType, PDO, PHPUnit\Framework\TestCase, PublishRequest, ResourceLimitException, Connection

### Community 8 - "Application.php"
Cohesion: 0.13
Nodes (5): Flux\Broker\DeliveryState, self, RetryPolicy, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.18
Nodes (7): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO, DeliveryStateException, self

### Community 12 - "Broker"
Cohesion: 0.17
Nodes (3): Broker, self, VirtualHostNotFoundException

### Community 16 - "UserRepository"
Cohesion: 0.12
Nodes (6): AuthenticationService, AuthorizationService, Authenticator, Authorizer, User, UserRepository

### Community 17 - "DateTimeImmutable"
Cohesion: 0.14
Nodes (4): DateTimeImmutable, Flux\Runtime\RuntimeState, ReleaseRequest, RuntimeRegistrationException

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 20 - "DestinationRepository"
Cohesion: 0.13
Nodes (6): Flux\Broker\DestinationType, Destination, DestinationType, QueueStatus, DestinationRepository, DestinationType

### Community 21 - "BrokerRuntimeTest"
Cohesion: 0.15
Nodes (3): Flux\Runtime\RuntimeDrainingComponent, BrokerRuntimeTest, RecordingDrainingComponent

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AuthenticatedUser"
Cohesion: 0.13
Nodes (8): Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), ListenerAuthenticationService, InMemoryAuthenticationService

### Community 25 - "Migrator"
Cohesion: 0.15
Nodes (4): SplFileInfo, MigrationResult, Migrator, MigrationFilenameTest

### Community 26 - "AcknowledgeRequest"
Cohesion: 0.12
Nodes (4): AcknowledgeRequest, RejectRequest, ReserveRequest, DeliveryRequestTest

### Community 27 - "VirtualHostRepository"
Cohesion: 0.14
Nodes (3): VirtualHost, VirtualHostRepository, VirtualHostRepositoryTest

### Community 36 - "ResourceLimits"
Cohesion: 0.15
Nodes (4): Closure, self, ResourceLimits, ResourceLimitsTest

### Community 38 - "Uuid"
Cohesion: 0.18
Nodes (4): self, self, Uuid, UuidTest

### Community 42 - "ReadOnlyDatabaseContext"
Cohesion: 0.23
Nodes (3): BindingListCommand, ReadOnlyDatabaseContext, SubscriptionListCommand

### Community 46 - "AuthorizationResult"
Cohesion: 0.20
Nodes (5): AuthorizationResult, self, authorize(), AuthorizationPermission, AuthorizationPermission

### Community 47 - "PublishTransaction"
Cohesion: 0.35
Nodes (3): PDO, RoutingSourceType, PublishTransaction

### Community 48 - "RuntimeConnection"
Cohesion: 0.24
Nodes (3): RuntimeConnection, RuntimeConsumer, RuntimeModelTest

### Community 50 - "RoutingSource"
Cohesion: 0.40
Nodes (3): RoutingSourceType, RoutingSourceType, RoutingSource

### Community 66 - "UserCreateCommand"
Cohesion: 0.36
Nodes (3): Closure, Closure, UserCreateCommand

## Knowledge Gaps
- **16 isolated node(s):** `sort-packages`, `description`, `license`, `minimum-stability`, `name` (+11 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **53 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `Frame`, `ConnectionConfig`, `RuntimeException`, `BindingRepository`, `Application.php`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `DateTimeImmutable`, `BrokerDeliveryTest`, `DestinationRepository`, `AdminCommandTest`, `Migrator`, `VirtualHostRepository`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `MessageRepository`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ResourceLimits`, `MessageRouteRepository`, `BrokerRuntimeIntegrationTest`, `ConnectionTest`, `ReadOnlyDatabaseContext`, `RoutingSourceRepository`, `DestinationRepositoryTest`, `PublishTransaction`, `ReadinessCommandTest`, `.createRuntime`, `UserCreateCommand`, `UserClearPermissionsCommand`, `UserGrantVhostCommand`, `UserListCommand`, `UserListPermissionsCommand`, `UserListVhostsCommand`, `UserSetPermissionsCommand`, `VhostCreateCommand`?**
  _High betweenness centrality (0.257) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `Connection`?**
  _High betweenness centrality (0.116) - this node is a cross-community bridge._
- **Why does `AmqpConnection` connect `AmqpConnection` to `Frame`, `ResourceLimits`, `Connection`, `AmqpConnectionTest`, `Broker`, `RuntimeConnection`, `DateTimeImmutable`, `ConsumerRegistry`, `AuthenticatedUser`, `ConnectionRegistry`?**
  _High betweenness centrality (0.102) - this node is a cross-community bridge._
- **Are the 18 inferred relationships involving `Frame` (e.g. with `.completeHandshake()` and `.testChannelCanOpenAndCloseAfterConnectionHandshake()`) actually correct?**
  _`Frame` has 18 INFERRED edges - model-reasoned connections that need verification._
- **Are the 12 inferred relationships involving `Connection` (e.g. with `.userClearPermissions()` and `.userCreate()`) actually correct?**
  _`Connection` has 12 INFERRED edges - model-reasoned connections that need verification._
- **What connects `sort-packages`, `description`, `license` to the rest of the system?**
  _16 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09198113207547169 - nodes in this community are weakly interconnected._