# Graph Report - flux  (2026-08-23)

## Corpus Check
- 163 files · ~63,173 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1690 nodes · 5274 edges · 93 communities (45 shown, 48 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `f31e0982`
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
- DestinationRepository
- DeliveryRepository
- SchemaTest
- AmqpConnectionTest
- PublishTransactionTest
- BrokerTopologyManagementTest
- DeliveryRepositoryTest
- UserRepository
- Broker
- composer.json
- BrokerDeliveryTest
- Destination
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- AuthenticatedUser
- .writeFrame
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- AmqpMethodReader
- SubscriptionRepository
- ConnectionConfig
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServer
- MessageRepository
- DateTimeImmutable
- Uuid
- BrokerRuntimeIntegrationTest
- ConnectionTest
- BrokerRuntime
- Application.php
- BrokerPublishTest
- Dotenv
- DestinationRepositoryTest
- AuthorizationResult
- BindingRepositoryTest
- RuntimeConnection
- ConsumerRegistry
- RoutingSourceRepository
- ResourcePermissionMatcher
- TopicMatcher
- Flux
- BrokerStatsCommand
- ConnectionRegistry
- UserRepository.php
- PublishRequestTest
- RuntimeDiagnosticsServerTest
- RoutingSource
- ExclusiveQueueRegistry
- VirtualHostNotFoundException
- .createRuntime
- UserCreateCommand
- ConsumerRegistryTest
- UserPermissions
- ApplicationTest
- ConnectionRegistryTest
- QueueShowCommand
- RuntimeDiagnosticsClient
- RecordingRuntimeComponent
- DbStatusCommand
- Flux MVP Smoke Test
- ResourceLimitsTest
- RuntimeException
- 20260820_120000_create_schema_migrations.sql

## God Nodes (most connected - your core abstractions)
1. `AmqpPublishConsumeTest` - 174 edges
2. `Frame` - 154 edges
3. `Connection` - 137 edges
4. `AmqpConnection` - 115 edges
5. `Broker` - 74 edges
6. `AmqpTopologyTest` - 62 edges
7. `AmqpListener` - 53 edges
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

## Communities (93 total, 48 thin omitted)

### Community 1 - "AmqpConnection"
Cohesion: 0.11
Nodes (3): AmqpConnectionState, Flux\Broker\AuthorizationService, AmqpConnection

### Community 2 - "Frame"
Cohesion: 0.10
Nodes (5): Frame, self, ProtocolException, AmqpTopologyTest, FrameCodecTest

### Community 4 - "AmqpListener"
Cohesion: 0.09
Nodes (3): AmqpListener, AmqpTlsConfig, AmqpListenerTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.05
Nodes (11): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, ReadinessCommand, AvailableRuntimeDiagnostics, UnavailableRuntimeDiagnostics, ReadinessCommandTest (+3 more)

### Community 7 - "Connection"
Cohesion: 0.08
Nodes (13): DateTimeZone, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, SplFileInfo, UserClearPermissionsCommand, UserGrantVhostCommand (+5 more)

### Community 8 - "DestinationRepository"
Cohesion: 0.09
Nodes (8): Flux\Broker\DestinationType, UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, Table, DestinationRepository, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.20
Nodes (6): Flux\Broker\DeliveryState, Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO

### Community 10 - "SchemaTest"
Cohesion: 0.13
Nodes (3): MigrationResult, PDO, SchemaTest

### Community 16 - "UserRepository"
Cohesion: 0.16
Nodes (5): AuthenticationService, AuthorizationService, Authenticator, Authorizer, UserRepository

### Community 17 - "Broker"
Cohesion: 0.09
Nodes (15): Closure, Flux\Broker\AuthorizationPermission, Flux\Broker\RoutingSourceType, Flux\Runtime\RuntimeDrainingComponent, Flux\Runtime\RuntimeState, Broker, Closure, PublishRequest (+7 more)

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 20 - "Destination"
Cohesion: 0.13
Nodes (4): Destination, DestinationType, QueueStatus, DestinationType

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AuthenticatedUser"
Cohesion: 0.10
Nodes (10): Flux\Broker\AuthenticationService, Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), FrameCodec (+2 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 32 - "ConnectionConfig"
Cohesion: 0.12
Nodes (4): MigrateCommand, self, ConnectionConfig, self

### Community 36 - "MessageRepository"
Cohesion: 0.14
Nodes (3): Message, PublishResult, MessageRepository

### Community 37 - "DateTimeImmutable"
Cohesion: 0.09
Nodes (5): DateTimeImmutable, MessageRoute, ReleaseRequest, VirtualHost, MessageRouteRepository

### Community 38 - "Uuid"
Cohesion: 0.13
Nodes (4): self, self, Uuid, UuidTest

### Community 42 - "Application.php"
Cohesion: 0.11
Nodes (6): BindingListCommand, MessagePeekCommand, QueueListCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, VhostListCommand

### Community 46 - "AuthorizationResult"
Cohesion: 0.20
Nodes (5): AuthorizationResult, self, authorize(), AuthorizationPermission, AuthorizationPermission

### Community 48 - "RuntimeConnection"
Cohesion: 0.29
Nodes (4): RuntimeConnection, RuntimeConsumer, RuntimeRegistrationException, RuntimeModelTest

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 61 - "RoutingSource"
Cohesion: 0.40
Nodes (3): RoutingSourceType, RoutingSourceType, RoutingSource

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

### Community 82 - "RuntimeException"
Cohesion: 0.06
Nodes (16): InvalidArgumentException, JsonException, RuntimeException, AcknowledgeRequest, DestinationNotFoundException, self, RejectRequest, ReserveRequest (+8 more)

## Knowledge Gaps
- **47 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+42 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **48 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `Frame`, `Application`, `Flux\Runtime\RuntimeDiagnostics`, `BindingRepository`, `DestinationRepository`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `Broker`, `BrokerDeliveryTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `SubscriptionRepository`, `ConnectionConfig`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `MessageRepository`, `DateTimeImmutable`, `BrokerRuntimeIntegrationTest`, `ConnectionTest`, `Application.php`, `BrokerPublishTest`, `DestinationRepositoryTest`, `BindingRepositoryTest`, `RoutingSourceRepository`, `.createRuntime`, `UserCreateCommand`, `DbStatusCommand`, `RuntimeException`?**
  _High betweenness centrality (0.181) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `Broker`, `Connection`?**
  _High betweenness centrality (0.135) - this node is a cross-community bridge._
- **Why does `Frame` connect `Frame` to `.shortString`, `AmqpConnection`, `AmqpPublishConsumeTest`, `AmqpListener`, `AmqpConnectionTest`, `Broker`, `.handleBasicCancel`, `AuthenticatedUser`, `.writeFrame`, `.sendChannelError`?**
  _High betweenness centrality (0.069) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08723770209838322 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.10512820512820513 - nodes in this community are weakly interconnected._
- **Should `Frame` be split into smaller, more focused modules?**
  _Cohesion score 0.1008991008991009 - nodes in this community are weakly interconnected._