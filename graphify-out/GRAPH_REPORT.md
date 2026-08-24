# Graph Report - flux  (2026-08-24)

## Corpus Check
- 164 files · ~65,496 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1716 nodes · 5431 edges · 87 communities (46 shown, 41 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d811efb6`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- AmqpPublishConsumeTest
- AmqpConnection
- Frame
- Application
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
- Migrator
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
- Authenticator
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
- BindingRepositoryTest
- DestinationRepository
- MessageRepository
- AuthorizationResult
- ResourcePermissionMatcher
- BrokerStatsCommand
- TopicMatcher
- Flux
- QueueShowCommand
- DbStatusCommand
- Authorizer
- PublishRequestTest
- ApplicationTest
- ConnectionConfig
- ConnectionRegistryTest.php
- ServerStartCommand
- UserCreateCommand
- ConsumerRegistryTest
- UserPermissions
- RuntimeDiagnosticsClient
- ConnectionRegistry
- Flux MVP Smoke Test
- RuntimeException
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

## Communities (87 total, 41 thin omitted)

### Community 2 - "Frame"
Cohesion: 0.10
Nodes (5): Frame, self, ProtocolException, AmqpTopologyTest, FrameCodecTest

### Community 4 - "AmqpListener"
Cohesion: 0.06
Nodes (5): AmqpListener, AmqpTlsConfig, TlsCertificate, AmqpListenerTest, BrokerRuntimeIntegrationTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.05
Nodes (11): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, ReadinessCommand, AvailableRuntimeDiagnostics, UnavailableRuntimeDiagnostics, ReadinessCommandTest (+3 more)

### Community 7 - "Connection"
Cohesion: 0.11
Nodes (12): DateTimeZone, Flux\Runtime\RuntimeState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, UserClearPermissionsCommand, UserGrantVhostCommand (+4 more)

### Community 8 - "Throwable"
Cohesion: 0.10
Nodes (6): UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, Table, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.14
Nodes (8): AcknowledgeRequest, Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO, DeliveryStateException, self

### Community 12 - "Broker"
Cohesion: 0.08
Nodes (8): Broker, RoutingSourceType, QueueStatus, RoutingSourceType, RoutingSource, self, VirtualHostNotFoundException, ExclusiveQueueRegistry

### Community 17 - "Migrator"
Cohesion: 0.14
Nodes (4): SplFileInfo, MigrationResult, Migrator, MigrationFilenameTest

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
Cohesion: 0.10
Nodes (10): Flux\Broker\AuthenticationService, Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), FrameCodec (+2 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 35 - "RuntimeDiagnosticsServer"
Cohesion: 0.15
Nodes (3): RuntimeComponent, RuntimeDiagnosticsServer, RuntimeDiagnosticsServerTest

### Community 36 - "ResourceLimits"
Cohesion: 0.08
Nodes (11): Flux\Broker\RoutingSourceType, Closure, ResourceLimitException, self, ResourceLimits, PDO, RoutingSourceType, PublishTransaction (+3 more)

### Community 37 - "DateTimeImmutable"
Cohesion: 0.09
Nodes (5): DateTimeImmutable, MessageRoute, ReleaseRequest, VirtualHost, MessageRouteRepository

### Community 38 - "Uuid"
Cohesion: 0.14
Nodes (4): self, RuntimeConsumer, Uuid, UuidTest

### Community 41 - "RuntimeConnection"
Cohesion: 0.22
Nodes (3): self, RuntimeConnection, RuntimeModelTest

### Community 42 - "Application.php"
Cohesion: 0.11
Nodes (6): BindingListCommand, MessagePeekCommand, QueueListCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, VhostListCommand

### Community 48 - "DestinationRepository"
Cohesion: 0.09
Nodes (9): Flux\Broker\DeliveryState, Flux\Broker\DestinationType, Destination, DestinationType, RejectRequest, self, RetryPolicy, DestinationRepository (+1 more)

### Community 49 - "MessageRepository"
Cohesion: 0.17
Nodes (3): Message, PublishResult, MessageRepository

### Community 50 - "AuthorizationResult"
Cohesion: 0.25
Nodes (4): AuthorizationResult, self, authorize(), AuthorizationPermission

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 57 - "Authorizer"
Cohesion: 0.33
Nodes (3): AuthorizationService, Authorizer, AuthorizationPermission

### Community 60 - "ConnectionConfig"
Cohesion: 0.12
Nodes (4): MigrateCommand, self, ConnectionConfig, self

### Community 73 - "ConnectionRegistry"
Cohesion: 0.12
Nodes (5): Flux\Broker\AuthorizationPermission, Flux\Broker\AuthorizationService, Flux\Runtime\RuntimeComponent, ConnectionRegistry, RecordingRuntimeComponent

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

### Community 82 - "RuntimeException"
Cohesion: 0.07
Nodes (12): Closure, InvalidArgumentException, JsonException, RuntimeException, DestinationNotFoundException, self, PublishRequest, ReserveRequest (+4 more)

## Knowledge Gaps
- **47 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+42 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **41 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `Frame`, `Application`, `AmqpListener`, `Flux\Runtime\RuntimeDiagnostics`, `BindingRepository`, `Throwable`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `Migrator`, `BrokerDeliveryTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ResourceLimits`, `DateTimeImmutable`, `ConnectionTest`, `Application.php`, `BrokerPublishTest`, `DestinationRepositoryTest`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `DbStatusCommand`, `ConnectionConfig`, `ServerStartCommand`, `UserCreateCommand`, `RuntimeException`?**
  _High betweenness centrality (0.173) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `AmqpListener`, `Connection`?**
  _High betweenness centrality (0.146) - this node is a cross-community bridge._
- **Why does `Broker` connect `Broker` to `AmqpPublishConsumeTest`, `AmqpConnection`, `Frame`, `AmqpListener`, `BindingRepository`, `Connection`, `DeliveryRepository`, `BrokerTopologyManagementTest`, `BrokerDeliveryTest`, `BrokerRuntimeTest`, `.writeFrame`, `ConsumerRegistry`, `SubscriptionRepository`, `ResourceLimits`, `DateTimeImmutable`, `BrokerRuntime`, `BrokerPublishTest`, `DestinationRepository`, `MessageRepository`, `ServerStartCommand`, `ConnectionRegistry`, `RuntimeException`?**
  _High betweenness centrality (0.068) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08084859052600989 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.09183673469387756 - nodes in this community are weakly interconnected._
- **Should `Frame` be split into smaller, more focused modules?**
  _Cohesion score 0.10126582278481013 - nodes in this community are weakly interconnected._