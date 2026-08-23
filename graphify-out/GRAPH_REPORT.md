# Graph Report - flux  (2026-08-23)

## Corpus Check
- 163 files · ~64,097 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1700 nodes · 5348 edges · 83 communities (40 shown, 43 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `6fb4e1bf`
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
- DiagnosticsCommandTest
- composer.json
- BrokerDeliveryTest
- ReadinessCommandTest
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- AmqpMethodReader
- .writeFrame
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- ReadinessCommand
- SubscriptionRepository
- Authenticator
- MessageRepositoryTest
- MessageRouteRepositoryTest
- ConsumerRegistry
- ResourceLimits
- DateTimeImmutable
- ConnectionRegistry
- MessagePeekCommand
- ConnectionTest
- AvailableRuntimeDiagnostics
- Application.php
- BrokerPublishTest
- Dotenv
- DestinationRepositoryTest
- ReadyRuntimeDiagnostics
- BindingRepositoryTest
- DestinationRepository
- MessageRepository
- ConnectionConfigTest
- ResourcePermissionMatcher
- Frame
- TopicMatcher
- Flux
- BrokerStatsCommand
- UserRepository.php
- PublishRequestTest
- ExclusiveQueueRegistry
- UserCreateCommand
- ConsumerRegistryTest
- UserPermissions
- QueueShowCommand
- RuntimeDiagnosticsClient
- RecordingRuntimeComponent
- Flux MVP Smoke Test
- RuntimeException
- 20260820_120000_create_schema_migrations.sql

## God Nodes (most connected - your core abstractions)
1. `AmqpPublishConsumeTest` - 180 edges
2. `Frame` - 157 edges
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

## Communities (83 total, 43 thin omitted)

### Community 1 - "AmqpConnection"
Cohesion: 0.11
Nodes (4): AmqpConnectionState, Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationService, AmqpConnection

### Community 3 - "ConnectionConfig"
Cohesion: 0.07
Nodes (8): Application, DbStatusCommand, MigrateCommand, self, ConnectionConfig, self, BrokerRuntimeIntegrationTest, ApplicationTest

### Community 4 - "AmqpListener"
Cohesion: 0.07
Nodes (4): AmqpListener, AmqpTlsConfig, TlsCertificate, AmqpListenerTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.11
Nodes (6): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, UnavailableRuntimeDiagnostics, FakeRuntimeDiagnostics

### Community 7 - "Connection"
Cohesion: 0.11
Nodes (13): DateTimeZone, Flux\Broker\DeliveryState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, UserGrantVhostCommand, UserSetPermissionsCommand (+5 more)

### Community 8 - "Throwable"
Cohesion: 0.09
Nodes (7): UserClearPermissionsCommand, UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, Table, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.16
Nodes (7): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO, DeliveryStateException, self

### Community 10 - "SchemaTest"
Cohesion: 0.10
Nodes (4): SplFileInfo, MigrationResult, PDO, SchemaTest

### Community 12 - "Broker"
Cohesion: 0.09
Nodes (9): Closure, Broker, RoutingSourceType, RoutingSourceType, RoutingSource, self, VirtualHostNotFoundException, RoutingSourceType (+1 more)

### Community 16 - "UserRepository"
Cohesion: 0.21
Nodes (3): AuthorizationService, Authorizer, UserRepository

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AmqpMethodReader"
Cohesion: 0.06
Nodes (16): Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), AuthorizationResult, self (+8 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 35 - "ConsumerRegistry"
Cohesion: 0.06
Nodes (8): RuntimeComponent, RuntimeState, ServerStartCommand, BrokerRuntime, Closure, ConsumerRegistry, RuntimeDiagnosticsServer, RuntimeDiagnosticsServerTest

### Community 36 - "ResourceLimits"
Cohesion: 0.09
Nodes (8): Closure, PublishResult, self, ResourceLimits, PDO, RoutingSourceType, PublishTransaction, ResourceLimitsTest

### Community 37 - "DateTimeImmutable"
Cohesion: 0.10
Nodes (5): DateTimeImmutable, MessageRoute, ReleaseRequest, VirtualHost, MessageRouteRepository

### Community 38 - "ConnectionRegistry"
Cohesion: 0.06
Nodes (13): Flux\Runtime\RuntimeDrainingComponent, Flux\Runtime\RuntimeState, PublishRequest, ConnectionRegistry, self, RuntimeConnection, self, RuntimeConsumer (+5 more)

### Community 42 - "Application.php"
Cohesion: 0.13
Nodes (5): BindingListCommand, QueueListCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, VhostListCommand

### Community 48 - "DestinationRepository"
Cohesion: 0.12
Nodes (6): Flux\Broker\DestinationType, Destination, DestinationType, QueueStatus, DestinationRepository, DestinationType

### Community 52 - "Frame"
Cohesion: 0.10
Nodes (3): Frame, self, FrameCodecTest

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

### Community 82 - "RuntimeException"
Cohesion: 0.06
Nodes (16): Flux\Broker\AuthorizationPermission, Flux\Broker\RoutingSourceType, InvalidArgumentException, JsonException, RuntimeException, AcknowledgeRequest, DestinationNotFoundException, self (+8 more)

## Knowledge Gaps
- **47 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+42 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **43 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `ConnectionConfig`, `BindingRepository`, `Throwable`, `DeliveryRepository`, `SchemaTest`, `Broker`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `ReadinessCommandTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `ReadinessCommand`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ConsumerRegistry`, `ResourceLimits`, `DateTimeImmutable`, `ConnectionRegistry`, `ConnectionTest`, `Application.php`, `BrokerPublishTest`, `DestinationRepositoryTest`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `UserCreateCommand`?**
  _High betweenness centrality (0.187) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `AmqpListener`, `Connection`?**
  _High betweenness centrality (0.126) - this node is a cross-community bridge._
- **Why does `Frame` connect `Frame` to `.shortString`, `AmqpConnection`, `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `AmqpListener`, `Connection`, `AmqpConnectionTest`, `AmqpMethodReader`, `.writeFrame`?**
  _High betweenness centrality (0.070) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.0862987012987013 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.11095305832147938 - nodes in this community are weakly interconnected._
- **Should `AmqpTopologyTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14377556984219755 - nodes in this community are weakly interconnected._