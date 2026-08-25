# Graph Report - flux  (2026-08-25)

## Corpus Check
- 165 files · ~66,375 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1733 nodes · 5455 edges · 93 communities (46 shown, 47 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `05ff8540`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- AmqpPublishConsumeTest
- AmqpConnection
- Frame
- Application
- AmqpListenerTest
- Flux\Runtime\RuntimeDiagnostics
- BindingRepository
- Connection
- Throwable
- DeliveryRepository
- SchemaTest
- .migrate
- Broker
- PublishTransactionTest
- BrokerTopologyManagementTest
- DeliveryRepositoryTest
- UserRepository
- AmqpListener
- composer.json
- BrokerDeliveryTest
- VirtualHostRepository
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- AmqpMethodReader
- ResourceLimits
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- ConsumerRegistry
- SubscriptionRepository
- AmqpListenerTest.php
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServer
- PublishTransaction
- RuntimeException
- Uuid
- Application.php
- ConnectionTest
- RuntimeConnection
- ConnectionConfig
- BrokerPublishTest
- Dotenv
- DestinationRepositoryTest
- DateTimeImmutable
- BindingRepositoryTest
- DestinationRepository
- MessageRepository
- RoutingSourceRepository
- DiagnosticsCommandTest
- BrokerStatsCommand
- TopicMatcher
- Flux
- ApplicationTest
- ReadinessCommandTest
- BrokerRuntime
- PublishRequestTest
- RoutingSource
- UserClearPermissionsCommand
- ExclusiveQueueRegistry
- DeliveryStateException
- AvailableRuntimeDiagnostics
- RuntimeDiagnosticsClient
- RecordingRuntimeComponent
- ConsumerRegistryTest
- Changelog
- UnavailableRuntimeDiagnostics
- BrokerRuntimeIntegrationTest
- DbStatusCommand
- Destination
- .forName
- UserPermissions
- Flux MVP Smoke Test
- UserCreateCommand
- ConnectionRegistryTest
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

## Communities (93 total, 47 thin omitted)

### Community 1 - "AmqpConnection"
Cohesion: 0.06
Nodes (5): AmqpConnectionState, TopologyException, AmqpConnection, AuthorizationPermission, AmqpConnectionTest

### Community 2 - "Frame"
Cohesion: 0.09
Nodes (6): Frame, self, FrameCodec, ProtocolException, AmqpTopologyTest, FrameCodecTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.11
Nodes (6): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, ReadyRuntimeDiagnostics, FakeRuntimeDiagnostics

### Community 6 - "BindingRepository"
Cohesion: 0.14
Nodes (3): Binding, Closure, BindingRepository

### Community 7 - "Connection"
Cohesion: 0.15
Nodes (11): DateTimeZone, Flux\Broker\DeliveryState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, Connection, self (+3 more)

### Community 8 - "Throwable"
Cohesion: 0.10
Nodes (6): UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, Table, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.24
Nodes (4): Delivery, DeliveryRepository, DeliveryState, PDO

### Community 16 - "UserRepository"
Cohesion: 0.11
Nodes (6): AuthenticationService, AuthorizationService, Authenticator, Authorizer, UserSetPermissionsCommand, UserRepository

### Community 17 - "AmqpListener"
Cohesion: 0.11
Nodes (5): Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationService, Flux\Runtime\RuntimeDrainingComponent, AmqpListener, ConnectionRegistry

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 20 - "VirtualHostRepository"
Cohesion: 0.15
Nodes (3): VirtualHost, VhostCreateCommand, VirtualHostRepository

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AmqpMethodReader"
Cohesion: 0.05
Nodes (16): Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), AuthorizationResult, self (+8 more)

### Community 25 - "ResourceLimits"
Cohesion: 0.16
Nodes (4): self, ResourceLimits, ServerStartCommand, ResourceLimitsTest

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 31 - "SubscriptionRepository"
Cohesion: 0.18
Nodes (3): Flux\Runtime\RuntimeState, Subscription, SubscriptionRepository

### Community 35 - "RuntimeDiagnosticsServer"
Cohesion: 0.16
Nodes (3): RuntimeComponent, RuntimeDiagnosticsServer, RuntimeDiagnosticsServerTest

### Community 36 - "PublishTransaction"
Cohesion: 0.14
Nodes (6): Flux\Broker\RoutingSourceType, PublishResult, ResourceLimitException, PDO, RoutingSourceType, PublishTransaction

### Community 37 - "RuntimeException"
Cohesion: 0.05
Nodes (16): Closure, Flux\Broker\AuthorizationPermission, InvalidArgumentException, JsonException, RuntimeException, AcknowledgeRequest, DestinationNotFoundException, self (+8 more)

### Community 38 - "Uuid"
Cohesion: 0.15
Nodes (4): self, self, Uuid, UuidTest

### Community 39 - "Application.php"
Cohesion: 0.09
Nodes (8): BindingListCommand, MessagePeekCommand, QueueListCommand, DeliveryState, QueueShowCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, VhostListCommand

### Community 41 - "RuntimeConnection"
Cohesion: 0.14
Nodes (4): RuntimeConnection, RuntimeConsumer, RuntimeRegistrationException, RuntimeModelTest

### Community 42 - "ConnectionConfig"
Cohesion: 0.11
Nodes (4): MigrateCommand, ReadinessCommand, ConnectionConfig, self

### Community 46 - "DateTimeImmutable"
Cohesion: 0.14
Nodes (4): DateTimeImmutable, DeliveryState, MessageRoute, User

### Community 48 - "DestinationRepository"
Cohesion: 0.12
Nodes (5): Flux\Broker\DestinationType, self, RetryPolicy, DestinationRepository, DestinationType

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 59 - "RoutingSource"
Cohesion: 0.40
Nodes (3): RoutingSourceType, RoutingSourceType, RoutingSource

### Community 68 - "Changelog"
Cohesion: 0.18
Nodes (10): [0.1.0] - 25 Aug 2026, [0.1.0-RC1] - 24 Aug 2026, [0.1.0-RC2] - 24 Aug 2026, [0.1.0-RC3] - 24 Aug 2026, Added, Changed, Changed, Changelog (+2 more)

### Community 72 - "Destination"
Cohesion: 0.31
Nodes (3): Destination, DestinationType, QueueStatus

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

## Knowledge Gaps
- **52 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+47 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **47 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `Frame`, `Application`, `BindingRepository`, `Throwable`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `VirtualHostRepository`, `AdminCommandTest`, `ResourceLimits`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `PublishTransaction`, `RuntimeException`, `Application.php`, `ConnectionTest`, `ConnectionConfig`, `BrokerPublishTest`, `DestinationRepositoryTest`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `RoutingSourceRepository`, `ReadinessCommandTest`, `UserClearPermissionsCommand`, `BrokerRuntimeIntegrationTest`, `DbStatusCommand`, `UserCreateCommand`?**
  _High betweenness centrality (0.169) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `AmqpListenerTest.php`, `Connection`?**
  _High betweenness centrality (0.144) - this node is a cross-community bridge._
- **Why does `Frame` connect `Frame` to `AmqpListenerTest.php`, `AmqpConnection`, `AmqpPublishConsumeTest`, `AmqpListenerTest`, `Connection`, `UserRepository`, `AmqpMethodReader`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _52 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08084859052600989 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.055177928828468614 - nodes in this community are weakly interconnected._
- **Should `Frame` be split into smaller, more focused modules?**
  _Cohesion score 0.08935574229691877 - nodes in this community are weakly interconnected._