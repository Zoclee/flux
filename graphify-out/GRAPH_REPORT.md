# Graph Report - flux  (2026-08-23)

## Corpus Check
- 163 files · ~64,174 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1701 nodes · 5357 edges · 87 communities (41 shown, 46 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `7abd5b5d`
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
- Connection
- composer.json
- BrokerDeliveryTest
- RoutingSourceRepository
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- AmqpMethodReader
- .writeFrame
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- RoutingSource
- SubscriptionRepository
- Authenticator
- MessageRepositoryTest
- MessageRouteRepositoryTest
- ConsumerRegistry
- MessageRouteRepository
- VirtualHost
- DateTimeImmutable
- MessagePeekCommand
- ConnectionTest
- Destination
- Application.php
- BrokerPublishTest
- Dotenv
- DestinationRepositoryTest
- BrokerRuntimeIntegrationTest
- BindingRepositoryTest
- DestinationRepository
- MessageRepository
- ConnectionConfigTest
- ResourcePermissionMatcher
- Frame
- TopicMatcher
- Flux
- PublishResult
- DbStatusCommand
- UserRepository.php
- PublishRequestTest
- ApplicationTest
- MigrateCommand
- ConnectionRegistryTest
- ExclusiveQueueRegistry
- .forName
- .fromArray
- ConsumerRegistryTest
- UserPermissions
- RuntimeDiagnosticsClient
- RecordingRuntimeComponent
- Flux MVP Smoke Test
- .reserve
- 20260820_120000_create_schema_migrations.sql

## God Nodes (most connected - your core abstractions)
1. `AmqpPublishConsumeTest` - 180 edges
2. `Frame` - 158 edges
3. `Connection` - 137 edges
4. `AmqpConnection` - 115 edges
5. `Broker` - 74 edges
6. `AmqpTopologyTest` - 63 edges
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

## Communities (87 total, 46 thin omitted)

### Community 1 - "AmqpConnection"
Cohesion: 0.11
Nodes (4): AmqpConnectionState, Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationService, AmqpConnection

### Community 4 - "AmqpListener"
Cohesion: 0.07
Nodes (4): AmqpListener, AmqpTlsConfig, TlsCertificate, AmqpListenerTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.05
Nodes (11): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, ReadinessCommand, AvailableRuntimeDiagnostics, UnavailableRuntimeDiagnostics, ReadinessCommandTest (+3 more)

### Community 7 - "PHPUnit\Framework\TestCase"
Cohesion: 0.13
Nodes (10): DateTimeZone, Flux\Broker\DeliveryState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, SplFileInfo, Migrator (+2 more)

### Community 8 - "Table"
Cohesion: 0.19
Nodes (3): UserListCommand, UserListVhostsCommand, Table

### Community 9 - "DeliveryRepository"
Cohesion: 0.17
Nodes (7): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO, DeliveryStateException, self

### Community 10 - "SchemaTest"
Cohesion: 0.13
Nodes (3): MigrationResult, PDO, SchemaTest

### Community 16 - "UserRepository"
Cohesion: 0.21
Nodes (3): AuthorizationService, Authorizer, UserRepository

### Community 17 - "Connection"
Cohesion: 0.08
Nodes (7): UserClearPermissionsCommand, UserGrantVhostCommand, UserListPermissionsCommand, UserSetPermissionsCommand, VhostCreateCommand, Connection, self

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 21 - "BrokerRuntimeTest"
Cohesion: 0.15
Nodes (3): Flux\Runtime\RuntimeDrainingComponent, BrokerRuntimeTest, RecordingDrainingComponent

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AmqpMethodReader"
Cohesion: 0.06
Nodes (16): Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), AuthorizationResult, self (+8 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 30 - "RoutingSource"
Cohesion: 0.40
Nodes (3): RoutingSourceType, RoutingSourceType, RoutingSource

### Community 35 - "ConsumerRegistry"
Cohesion: 0.05
Nodes (11): Closure, RuntimeComponent, RuntimeState, ServerStartCommand, Closure, UserCreateCommand, BrokerRuntime, Closure (+3 more)

### Community 36 - "MessageRouteRepository"
Cohesion: 0.08
Nodes (10): Flux\Broker\RoutingSourceType, Closure, ResourceLimitException, self, ResourceLimits, MessageRouteRepository, PDO, RoutingSourceType (+2 more)

### Community 38 - "DateTimeImmutable"
Cohesion: 0.06
Nodes (18): DateTimeImmutable, Flux\Broker\AuthorizationPermission, Flux\Runtime\RuntimeState, InvalidArgumentException, JsonException, RuntimeException, MessageRoute, PublishRequest (+10 more)

### Community 41 - "Destination"
Cohesion: 0.27
Nodes (3): Destination, DestinationType, QueueStatus

### Community 42 - "Application.php"
Cohesion: 0.08
Nodes (9): BindingListCommand, BrokerStatsCommand, DeliveryState, QueueListCommand, DeliveryState, QueueShowCommand, ReadOnlyDatabaseContext, SubscriptionListCommand (+1 more)

### Community 48 - "DestinationRepository"
Cohesion: 0.08
Nodes (8): Flux\Broker\DestinationType, RejectRequest, self, RetryPolicy, DestinationRepository, DestinationType, MigrationFailure, Throwable

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

### Community 82 - ".reserve"
Cohesion: 0.10
Nodes (7): AcknowledgeRequest, DestinationNotFoundException, self, ReserveRequest, self, SubscriptionNotFoundException, DeliveryRequestTest

## Knowledge Gaps
- **47 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+42 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `ConnectionConfig`, `Flux\Runtime\RuntimeDiagnostics`, `BindingRepository`, `PHPUnit\Framework\TestCase`, `Table`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `RoutingSourceRepository`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ConsumerRegistry`, `MessageRouteRepository`, `VirtualHost`, `DateTimeImmutable`, `ConnectionTest`, `Application.php`, `BrokerPublishTest`, `DestinationRepositoryTest`, `BrokerRuntimeIntegrationTest`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `DbStatusCommand`, `MigrateCommand`?**
  _High betweenness centrality (0.190) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `Connection`, `AmqpListener`, `PHPUnit\Framework\TestCase`?**
  _High betweenness centrality (0.126) - this node is a cross-community bridge._
- **Why does `Broker` connect `Broker` to `AmqpPublishConsumeTest`, `AmqpConnection`, `AmqpTopologyTest`, `AmqpListener`, `BindingRepository`, `PHPUnit\Framework\TestCase`, `DeliveryRepository`, `BrokerTopologyManagementTest`, `BrokerDeliveryTest`, `RoutingSourceRepository`, `BrokerRuntimeTest`, `.writeFrame`, `RoutingSource`, `SubscriptionRepository`, `ConsumerRegistry`, `MessageRouteRepository`, `DateTimeImmutable`, `Destination`, `BrokerPublishTest`, `DestinationRepository`, `MessageRepository`, `ExclusiveQueueRegistry`, `.reserve`?**
  _High betweenness centrality (0.071) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.0862987012987013 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.11095305832147938 - nodes in this community are weakly interconnected._
- **Should `AmqpTopologyTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14350282485875707 - nodes in this community are weakly interconnected._