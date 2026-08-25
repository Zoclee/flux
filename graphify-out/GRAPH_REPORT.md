# Graph Report - flux  (2026-08-25)

## Corpus Check
- 165 files · ~66,358 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1731 nodes · 5453 edges · 90 communities (45 shown, 45 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8d84485d`
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
- Connection
- DeliveryRepository
- SchemaTest
- .migrate
- Broker
- PublishTransactionTest
- BrokerTopologyManagementTest
- DeliveryRepositoryTest
- UserRepository
- AmqpConnectionTest
- composer.json
- BrokerDeliveryTest
- AmqpMethodReader
- BrokerRuntimeTest
- AdminCommandTest
- virtual_hosts
- ConnectionRegistry
- .writeFrame
- AGENTS.md
- VirtualHostRepositoryTest
- UserRepositoryTest
- SubscriptionRepositoryTest
- ConsumerRegistry
- SubscriptionRepository
- Frame
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServer
- ResourceLimits
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
- RetryPolicy
- PublishRequestTest
- RoutingSource
- ReadinessCommand
- ExclusiveQueueRegistry
- DeliveryStateException
- AvailableRuntimeDiagnostics
- RuntimeDiagnosticsClient
- RecordingRuntimeComponent
- ConsumerRegistryTest
- Changelog
- UnavailableRuntimeDiagnostics
- ResourcePermissionMatcher
- DbStatusCommand
- .queueStatus
- .forName
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

## Communities (90 total, 45 thin omitted)

### Community 4 - "AmqpListener"
Cohesion: 0.06
Nodes (5): AmqpListener, AmqpTlsConfig, TlsCertificate, AmqpListenerTest, BrokerRuntimeIntegrationTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.11
Nodes (6): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, ReadyRuntimeDiagnostics, FakeRuntimeDiagnostics

### Community 7 - "PHPUnit\Framework\TestCase"
Cohesion: 0.15
Nodes (11): DateTimeZone, Flux\Broker\DeliveryState, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, PublishRequest, MessageRouteRepository (+3 more)

### Community 8 - "Connection"
Cohesion: 0.06
Nodes (12): UserClearPermissionsCommand, UserGrantVhostCommand, UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, UserSetPermissionsCommand, VhostCreateCommand, Table (+4 more)

### Community 9 - "DeliveryRepository"
Cohesion: 0.21
Nodes (5): Delivery, DeliveryState, DeliveryRepository, DeliveryState, PDO

### Community 16 - "UserRepository"
Cohesion: 0.12
Nodes (3): AuthenticationService, Authenticator, UserRepository

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 21 - "BrokerRuntimeTest"
Cohesion: 0.15
Nodes (3): Flux\Runtime\RuntimeDrainingComponent, BrokerRuntimeTest, RecordingDrainingComponent

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "ConnectionRegistry"
Cohesion: 0.07
Nodes (13): Flux\Broker\AuthenticationService, Flux\Broker\AuthorizationPermission, Flux\Broker\AuthorizationService, Flux\Protocol\Amqp\AmqpConnectionState, AuthenticatedUser, AuthenticationResult, self, authenticate() (+5 more)

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 30 - "ConsumerRegistry"
Cohesion: 0.08
Nodes (8): Closure, RuntimeState, ServerStartCommand, Closure, UserCreateCommand, BrokerRuntime, Closure, ConsumerRegistry

### Community 31 - "SubscriptionRepository"
Cohesion: 0.18
Nodes (3): Closure, Subscription, SubscriptionRepository

### Community 32 - "Frame"
Cohesion: 0.10
Nodes (4): Frame, self, ProtocolException, FrameCodecTest

### Community 35 - "RuntimeDiagnosticsServer"
Cohesion: 0.16
Nodes (3): RuntimeComponent, RuntimeDiagnosticsServer, RuntimeDiagnosticsServerTest

### Community 36 - "ResourceLimits"
Cohesion: 0.08
Nodes (11): AuthorizationService, Flux\Broker\RoutingSourceType, Flux\Runtime\RuntimeState, Authorizer, PublishResult, self, ResourceLimits, PDO (+3 more)

### Community 37 - "RuntimeException"
Cohesion: 0.07
Nodes (12): InvalidArgumentException, JsonException, RuntimeException, AcknowledgeRequest, DestinationNotFoundException, self, ReserveRequest, ResourceLimitException (+4 more)

### Community 38 - "Uuid"
Cohesion: 0.14
Nodes (4): self, RuntimeConsumer, Uuid, UuidTest

### Community 39 - "Application.php"
Cohesion: 0.09
Nodes (8): BindingListCommand, MessagePeekCommand, QueueListCommand, DeliveryState, QueueShowCommand, ReadOnlyDatabaseContext, SubscriptionListCommand, VhostListCommand

### Community 41 - "RuntimeConnection"
Cohesion: 0.13
Nodes (5): self, RuntimeConnection, RuntimeRegistrationException, ConnectionRegistryTest, RuntimeModelTest

### Community 42 - "ConnectionConfig"
Cohesion: 0.14
Nodes (3): MigrateCommand, ConnectionConfig, self

### Community 46 - "DateTimeImmutable"
Cohesion: 0.11
Nodes (6): DateTimeImmutable, MessageRoute, ReleaseRequest, User, UserPermissions, VirtualHost

### Community 48 - "DestinationRepository"
Cohesion: 0.15
Nodes (5): Flux\Broker\DestinationType, Destination, DestinationType, DestinationRepository, DestinationType

### Community 53 - "TopicMatcher"
Cohesion: 0.24
Nodes (3): PHPUnit\Framework\Attributes\DataProvider, TopicMatcher, TopicMatcherTest

### Community 54 - "Flux"
Cohesion: 0.15
Nodes (12): Broker API, CLI, Database Migrations, Directory Structure, Flux, Installation, Known Limitations, MVP Capabilities (+4 more)

### Community 57 - "RetryPolicy"
Cohesion: 0.20
Nodes (3): RejectRequest, self, RetryPolicy

### Community 59 - "RoutingSource"
Cohesion: 0.40
Nodes (3): RoutingSourceType, RoutingSourceType, RoutingSource

### Community 68 - "Changelog"
Cohesion: 0.22
Nodes (8): [0.1.0-RC1] - 24 Aug 2026, [0.1.0-RC2] - 24 Aug 2026, [0.1.0-RC3] - 24 Aug 2026, Added, Changed, Changelog, Fixed, Known Limitations

### Community 70 - "ResourcePermissionMatcher"
Cohesion: 0.12
Nodes (7): AuthorizationResult, self, authorize(), AuthorizationPermission, AuthorizationPermission, ResourcePermissionMatcher, ResourcePermissionMatcherTest

### Community 75 - "Flux MVP Smoke Test"
Cohesion: 0.50
Nodes (3): AMQP Check, Flux MVP Smoke Test, Setup

## Knowledge Gaps
- **51 isolated node(s):** `name`, `description`, `type`, `license`, `flux` (+46 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **45 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Connection` connect `Connection` to `AmqpPublishConsumeTest`, `AmqpTopologyTest`, `Application`, `AmqpListener`, `BindingRepository`, `PHPUnit\Framework\TestCase`, `DeliveryRepository`, `SchemaTest`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `BrokerDeliveryTest`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `ConsumerRegistry`, `SubscriptionRepository`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `ResourceLimits`, `Application.php`, `ConnectionTest`, `ConnectionConfig`, `BrokerPublishTest`, `DestinationRepositoryTest`, `DateTimeImmutable`, `BindingRepositoryTest`, `DestinationRepository`, `MessageRepository`, `RoutingSourceRepository`, `ReadinessCommandTest`, `ReadinessCommand`, `DbStatusCommand`?**
  _High betweenness centrality (0.170) - this node is a cross-community bridge._
- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `Connection`, `AmqpListener`, `PHPUnit\Framework\TestCase`?**
  _High betweenness centrality (0.144) - this node is a cross-community bridge._
- **Why does `Frame` connect `Frame` to `AmqpPublishConsumeTest`, `AmqpConnection`, `AmqpTopologyTest`, `AmqpListener`, `PHPUnit\Framework\TestCase`, `AmqpConnectionTest`, `ConnectionRegistry`, `.writeFrame`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _51 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08084859052600989 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.10782241014799154 - nodes in this community are weakly interconnected._
- **Should `AmqpTopologyTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14350282485875707 - nodes in this community are weakly interconnected._