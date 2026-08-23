# Graph Report - flux  (2026-08-23)

## Corpus Check
- 163 files · ~63,168 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1715 nodes · 5273 edges · 85 communities (42 shown, 43 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 17 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `1569f009`
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
- .assertQueueAccess
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
- MessageRepository
- ConnectionConfig
- MessageRepositoryTest
- MessageRouteRepositoryTest
- RuntimeDiagnosticsServer
- ResourceLimitsTest
- DateTimeImmutable
- Uuid
- BrokerRuntimeIntegrationTest
- ConnectionTest
- BrokerRuntime
- Application.php
- RoutingSourceRepository
- Dotenv
- DestinationRepositoryTest
- AuthorizationResult
- BrokerPublishTest
- RuntimeConnection
- ConsumerRegistry
- RoutingSource
- ResourcePermissionMatcher
- ExclusiveQueueRegistry
- TopicMatcher
- Flux
- BrokerStatsCommand
- ConnectionRegistry
- BindingRepositoryTest
- PublishRequestTest
- RuntimeDiagnosticsServerTest
- UserPermissions
- .handleBasicCancel
- .createRuntime
- UserCreateCommand
- ConsumerRegistryTest
- ApplicationTest
- ConnectionRegistryTest
- RuntimeDiagnosticsClient
- RecordingRuntimeComponent
- DbStatusCommand
- Flux MVP Smoke Test
- RuntimeException
- 20260820_120000_create_schema_migrations.sql

## God Nodes (most connected - your core abstractions)
1. `AmqpPublishConsumeTest` - 174 edges
2. `Connection` - 134 edges
3. `AmqpConnection` - 114 edges
4. `Frame` - 69 edges
5. `Broker` - 68 edges
6. `AmqpTopologyTest` - 62 edges
7. `ConnectionConfig` - 48 edges
8. `DestinationRepository` - 48 edges
9. `DeliveryRepository` - 44 edges
10. `PublishTransactionTest` - 38 edges

## Surprising Connections (you probably didn't know these)
- `SchemaTest` --references--> `Connection`  [EXTRACTED]
  tests/Integration/Postgres/SchemaTest.php → src/Persistence/Postgres/Connection.php
- `PublishTransactionTest` --references--> `BindingRepository`  [EXTRACTED]
  tests/Integration/Postgres/PublishTransactionTest.php → src/Persistence/Postgres/BindingRepository.php
- `PublishTransactionTest` --references--> `Connection`  [EXTRACTED]
  tests/Integration/Postgres/PublishTransactionTest.php → src/Persistence/Postgres/Connection.php
- `PublishTransactionTest` --references--> `DestinationRepository`  [EXTRACTED]
  tests/Integration/Postgres/PublishTransactionTest.php → src/Persistence/Postgres/DestinationRepository.php
- `PublishTransactionTest` --references--> `MessageRepository`  [EXTRACTED]
  tests/Integration/Postgres/PublishTransactionTest.php → src/Persistence/Postgres/MessageRepository.php

## Import Cycles
- None detected.

## Communities (85 total, 43 thin omitted)

### Community 0 - "AmqpPublishConsumeTest"
Cohesion: 0.09
Nodes (6): Connection, Flux\Protocol\Amqp\AmqpListener, Flux\Protocol\Amqp\Frame, AmqpPublishConsumeTest, Delivery, Frame

### Community 1 - "AmqpConnection"
Cohesion: 0.06
Nodes (24): AmqpConnectionState, AmqpMethodReader, AuthorizationPermission, Flux\Broker\AuthenticatedUser, Flux\Broker\Authenticator, Flux\Broker\AuthorizationPermission, Flux\Broker\AuthorizationService, Flux\Broker\Authorizer (+16 more)

### Community 2 - "Frame"
Cohesion: 0.06
Nodes (8): Flux\Protocol\Amqp\AmqpConnectionState, Frame, self, FrameCodec, ProtocolException, AmqpTopologyTest, AmqpConnectionTest, FrameCodecTest

### Community 5 - "Flux\Runtime\RuntimeDiagnostics"
Cohesion: 0.09
Nodes (7): Flux\Runtime\RuntimeDiagnostics, ConnectionListCommand, ConsumerListCommand, HealthCommand, UnavailableRuntimeDiagnostics, ReadyRuntimeDiagnostics, FakeRuntimeDiagnostics

### Community 6 - "BindingRepository"
Cohesion: 0.06
Nodes (4): Binding, BindingRepository, BrokerPublishTest, BindingRepositoryTest

### Community 7 - "Connection"
Cohesion: 0.10
Nodes (12): DateTimeZone, PDO, PDOException, PHPUnit\Framework\Attributes\Before, PHPUnit\Framework\TestCase, UserClearPermissionsCommand, UserSetPermissionsCommand, VhostCreateCommand (+4 more)

### Community 8 - "DestinationRepository"
Cohesion: 0.09
Nodes (7): UserListCommand, UserListPermissionsCommand, UserListVhostsCommand, VhostListCommand, Table, MigrationFailure, Throwable

### Community 9 - "DeliveryRepository"
Cohesion: 0.15
Nodes (8): Flux\Broker\DeliveryState, Delivery, DeliveryState, self, RetryPolicy, DeliveryRepository, DeliveryState, PDO

### Community 10 - "SchemaTest"
Cohesion: 0.10
Nodes (4): SplFileInfo, MigrationResult, PDO, SchemaTest

### Community 11 - "AmqpConnectionTest"
Cohesion: 0.15
Nodes (3): Flux\Runtime\RuntimeComponent, Flux\Runtime\RuntimeDrainingComponent, AmqpListener

### Community 12 - ".assertQueueAccess"
Cohesion: 0.07
Nodes (10): Broker, RoutingSourceType, QueueStatus, RoutingSourceType, RoutingSource, self, VirtualHostNotFoundException, RoutingSourceType (+2 more)

### Community 16 - "UserRepository"
Cohesion: 0.11
Nodes (5): AuthenticationService, AuthorizationService, Authenticator, Authorizer, UserRepository

### Community 17 - "Broker"
Cohesion: 0.06
Nodes (12): Flux\Broker\RoutingSourceType, Closure, Message, PublishResult, ResourceLimitException, self, ResourceLimits, MessageRepository (+4 more)

### Community 18 - "composer.json"
Cohesion: 0.08
Nodes (24): autoload, autoload-dev, psr-4, psr-4, bin, config, sort-packages, description (+16 more)

### Community 20 - "Destination"
Cohesion: 0.16
Nodes (5): Flux\Broker\DestinationType, Destination, DestinationType, DestinationRepository, DestinationType

### Community 23 - "virtual_hosts"
Cohesion: 0.11
Nodes (11): virtual_hosts, destinations, bindings, messages, message_routes, subscriptions, deliveries, routing_sources (+3 more)

### Community 24 - "AuthenticatedUser"
Cohesion: 0.14
Nodes (8): Flux\Broker\AuthenticationService, AuthenticatedUser, AuthenticationResult, self, authenticate(), canAccessVirtualHost(), ListenerAuthenticationService, InMemoryAuthenticationService

### Community 26 - "AGENTS.md"
Cohesion: 0.09
Nodes (20): Architecture, Broker, CLI, Console, Core Philosophy, Dependencies, Documentation, graphify (+12 more)

### Community 27 - "VirtualHostRepositoryTest"
Cohesion: 0.13
Nodes (3): VirtualHost, VirtualHostRepository, VirtualHostRepositoryTest

### Community 31 - "MessageRepository"
Cohesion: 0.20
Nodes (3): Flux\Runtime\RuntimeState, Subscription, SubscriptionRepository

### Community 37 - "DateTimeImmutable"
Cohesion: 0.11
Nodes (6): DateTimeImmutable, MessageRoute, ReleaseRequest, User, UserPermissions, MessageRouteRepository

### Community 38 - "Uuid"
Cohesion: 0.15
Nodes (4): self, self, Uuid, UuidTest

### Community 41 - "BrokerRuntime"
Cohesion: 0.30
Nodes (3): Closure, RuntimeState, BrokerRuntime

### Community 42 - "Application.php"
Cohesion: 0.09
Nodes (7): BindingListCommand, MessagePeekCommand, QueueListCommand, DeliveryState, QueueShowCommand, ReadOnlyDatabaseContext, SubscriptionListCommand

### Community 46 - "AuthorizationResult"
Cohesion: 0.20
Nodes (5): AuthorizationResult, self, authorize(), AuthorizationPermission, AuthorizationPermission

### Community 48 - "RuntimeConnection"
Cohesion: 0.19
Nodes (3): RuntimeConnection, RuntimeConsumer, RuntimeModelTest

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
Cohesion: 0.05
Nodes (16): InvalidArgumentException, JsonException, RuntimeException, AcknowledgeRequest, DestinationNotFoundException, self, PublishRequest, RejectRequest (+8 more)

## Knowledge Gaps
- **47 isolated node(s):** `sort-packages`, `description`, `license`, `minimum-stability`, `name` (+42 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **43 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `AmqpPublishConsumeTest` connect `AmqpPublishConsumeTest` to `AmqpConnection`, `Connection`?**
  _High betweenness centrality (0.170) - this node is a cross-community bridge._
- **Why does `Connection` connect `Connection` to `Frame`, `Application`, `BindingRepository`, `DestinationRepository`, `DeliveryRepository`, `SchemaTest`, `.assertQueueAccess`, `PublishTransactionTest`, `BrokerTopologyManagementTest`, `DeliveryRepositoryTest`, `UserRepository`, `Broker`, `BrokerDeliveryTest`, `Destination`, `AdminCommandTest`, `VirtualHostRepositoryTest`, `UserRepositoryTest`, `SubscriptionRepositoryTest`, `MessageRepository`, `ConnectionConfig`, `MessageRepositoryTest`, `MessageRouteRepositoryTest`, `DateTimeImmutable`, `BrokerRuntimeIntegrationTest`, `ConnectionTest`, `Application.php`, `RoutingSourceRepository`, `DestinationRepositoryTest`, `BrokerPublishTest`, `BindingRepositoryTest`, `.createRuntime`, `UserCreateCommand`, `DbStatusCommand`?**
  _High betweenness centrality (0.139) - this node is a cross-community bridge._
- **Why does `AmqpConnection` connect `AmqpConnection` to `AuthenticatedUser`, `UserRepository`, `Frame`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **What connects `sort-packages`, `description`, `license` to the rest of the system?**
  _47 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AmqpPublishConsumeTest` be split into smaller, more focused modules?**
  _Cohesion score 0.08668831168831169 - nodes in this community are weakly interconnected._
- **Should `AmqpConnection` be split into smaller, more focused modules?**
  _Cohesion score 0.06086956521739131 - nodes in this community are weakly interconnected._
- **Should `Frame` be split into smaller, more focused modules?**
  _Cohesion score 0.06071323312702623 - nodes in this community are weakly interconnected._