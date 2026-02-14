<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LieferzeitenAdmin\Service\AdditionalDeliveryAssigneeMissingException;
use LieferzeitenAdmin\Service\LieferzeitenPositionWriteService;
use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use LieferzeitenAdmin\Service\Notification\TaskAssignmentRuleResolver;
use LieferzeitenAdmin\Service\WriteEndpointConflictException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LieferzeitenPositionWriteServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_paket (
            id BLOB PRIMARY KEY,
            status TEXT NULL,
            external_order_id TEXT NULL,
            source_system TEXT NULL,
            customer_email TEXT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_position (
            id BLOB PRIMARY KEY,
            paket_id BLOB NULL,
            position_number TEXT NULL,
            comment TEXT NULL,
            current_comment TEXT NULL,
            additional_delivery_request_at TEXT NULL,
            additional_delivery_request_initiator TEXT NULL,
            last_changed_by TEXT NULL,
            last_changed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_liefertermin_lieferant_history (
            position_id BLOB NOT NULL,
            liefertermin_from TEXT NULL,
            liefertermin_to TEXT NULL,
            liefertermin TEXT NULL,
            created_at TEXT NULL
        )');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_neuer_liefertermin_history (
            position_id BLOB NOT NULL,
            liefertermin_from TEXT NULL,
            liefertermin_to TEXT NULL,
            liefertermin TEXT NULL,
            created_at TEXT NULL
        )');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_neuer_liefertermin_paket_history (
            paket_id BLOB NOT NULL,
            liefertermin_from TEXT NULL,
            liefertermin_to TEXT NULL,
            liefertermin TEXT NULL,
            created_at TEXT NULL
        )');
    }

    public function testUpdateCommentWithTwoUsersDetectsOptimisticConflictAndReturnsRefreshData(): void
    {
        $positionId = '5ef25f3bd7094ee68d5943dba5d9ca30';
        $initialUpdatedAt = '2026-02-10 09:00:00.000';

        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'comment' => 'Initial comment',
            'current_comment' => 'Initial comment',
            'last_changed_by' => 'user-initial',
            'last_changed_at' => $initialUpdatedAt,
            'updated_at' => $initialUpdatedAt,
        ]);

        $positionRepository = $this->createPositionRepositoryMock();
        $service = $this->createService(positionRepository: $positionRepository);

        $contextUserA = Context::createDefaultContext();
        $contextUserB = Context::createDefaultContext();

        $service->updateComment($positionId, 'Comment from user A', $initialUpdatedAt, $contextUserA);

        $this->expectException(WriteEndpointConflictException::class);
        try {
            $service->updateComment($positionId, 'Comment from user B', $initialUpdatedAt, $contextUserB);
        } catch (WriteEndpointConflictException $e) {
            $refresh = $e->getRefresh();
            static::assertTrue(($refresh['exists'] ?? false) === true);
            static::assertSame('Comment from user A', $refresh['comment'] ?? null);
            static::assertSame('system', $refresh['lastChangedBy'] ?? null);
            static::assertIsString($refresh['updatedAt'] ?? null);

            throw $e;
        }
    }

    public function testCreateAdditionalDeliveryRequestUsesRuleAssigneeWhenConfigured(): void
    {
        $positionId = '74a417b9a3244e3d873dad86c64dd341';
        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'updated_at' => '2026-02-10 09:00:00.000',
        ]);

        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService
            ->expects(static::once())
            ->method('createTask')
            ->with(
                static::callback(static function (array $payload): bool {
                    return ($payload['initiatorDisplay'] ?? null) === 'manual'
                        && array_key_exists('initiatorUserId', $payload)
                        && ($payload['initiatorUserId'] ?? null) === null;
                }),
                static::isInstanceOf(Context::class),
                'manual',
                'rule-assignee',
                static::isInstanceOf(\DateTimeInterface::class),
            );

        $ruleResolver = $this->createMock(TaskAssignmentRuleResolver::class);
        $ruleResolver->method('resolve')->with(NotificationTriggerCatalog::ADDITIONAL_DELIVERY_DATE_REQUESTED, static::isInstanceOf(Context::class))
            ->willReturn(['assigneeIdentifier' => 'rule-assignee']);

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects(static::never())->method('get');

        $service = $this->createService(
            positionRepository: $this->createNoOpPositionRepository(),
            taskService: $taskService,
            ruleResolver: $ruleResolver,
            systemConfigService: $systemConfig,
        );

        $service->createAdditionalDeliveryRequest($positionId, 'manual', Context::createDefaultContext());
    }


    public function testCreateAdditionalDeliveryRequestUsesProvidedInitiatorWhenExplicitlySent(): void
    {
        $positionId = '2ca3ed2a8f584947af8715ef26b64e57';
        $contextUserId = 'd2f0c2db4cfe4fd39189a8f5d13d54d1';
        $providedUserId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'updated_at' => '2026-02-10 09:00:00.000',
        ]);

        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService
            ->expects(static::once())
            ->method('createTask')
            ->with(
                static::callback(static function (array $payload) use ($providedUserId): bool {
                    return ($payload['initiatorUserId'] ?? null) === $providedUserId
                        && ($payload['initiatorDisplay'] ?? null) === 'UI User';
                }),
                static::isInstanceOf(Context::class),
                'UI User',
                'rule-assignee',
                static::isInstanceOf(\DateTimeInterface::class),
            );

        $ruleResolver = $this->createMock(TaskAssignmentRuleResolver::class);
        $ruleResolver->method('resolve')->willReturn(['assigneeIdentifier' => 'rule-assignee']);

        $service = $this->createService(
            positionRepository: $this->createNoOpPositionRepository(),
            taskService: $taskService,
            ruleResolver: $ruleResolver,
            systemConfigService: $this->createMock(SystemConfigService::class),
        );

        $context = new Context(new AdminApiSource($contextUserId));
        $service->createAdditionalDeliveryRequest($positionId, null, $context, $providedUserId, 'UI User');
    }

    public function testCreateAdditionalDeliveryRequestFallsBackToContextUserWhenInitiatorMissing(): void
    {
        $positionId = 'b142f8bead7844b4a5e91f6f4af0d9f2';
        $contextUserId = 'd2f0c2db4cfe4fd39189a8f5d13d54d1';

        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'updated_at' => '2026-02-10 09:00:00.000',
        ]);

        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService
            ->expects(static::once())
            ->method('createTask')
            ->with(
                static::callback(static function (array $payload) use ($contextUserId): bool {
                    return ($payload['initiatorUserId'] ?? null) === $contextUserId
                        && ($payload['initiatorDisplay'] ?? null) === $contextUserId;
                }),
                static::isInstanceOf(Context::class),
                $contextUserId,
                'rule-assignee',
                static::isInstanceOf(\DateTimeInterface::class),
            );

        $ruleResolver = $this->createMock(TaskAssignmentRuleResolver::class);
        $ruleResolver->method('resolve')->willReturn(['assigneeIdentifier' => 'rule-assignee']);

        $service = $this->createService(
            positionRepository: $this->createNoOpPositionRepository(),
            taskService: $taskService,
            ruleResolver: $ruleResolver,
            systemConfigService: $this->createMock(SystemConfigService::class),
        );

        $context = new Context(new AdminApiSource($contextUserId));
        $service->createAdditionalDeliveryRequest($positionId, null, $context, null, null);
    }

    public function testCreateAdditionalDeliveryRequestFallsBackToConfiguredAssigneeWhenRuleMissing(): void
    {
        $positionId = 'f29fe9c6b24c4dbc87642744c935e5bc';
        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'updated_at' => '2026-02-10 09:00:00.000',
        ]);

        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService
            ->expects(static::once())
            ->method('createTask')
            ->with(
                static::isArray(),
                static::isInstanceOf(Context::class),
                'manual',
                'fallback-assignee',
                static::isInstanceOf(\DateTimeInterface::class),
            );

        $ruleResolver = $this->createMock(TaskAssignmentRuleResolver::class);
        $ruleResolver->method('resolve')->willReturn(null);

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->with('LieferzeitenAdmin.config.defaultAssigneeLieferterminAnfrageZusaetzlich')->willReturn(' fallback-assignee ');

        $service = $this->createService(
            positionRepository: $this->createNoOpPositionRepository(),
            taskService: $taskService,
            ruleResolver: $ruleResolver,
            systemConfigService: $systemConfig,
        );

        $service->createAdditionalDeliveryRequest($positionId, 'manual', Context::createDefaultContext());
    }

    public function testCreateAdditionalDeliveryRequestThrowsWhenRuleAndFallbackAssigneeAreMissing(): void
    {
        $positionId = '7e3f2a13f95f4707b1ef7fd4f02d1292';
        $this->connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'updated_at' => '2026-02-10 09:00:00.000',
        ]);

        $ruleResolver = $this->createMock(TaskAssignmentRuleResolver::class);
        $ruleResolver->method('resolve')->willReturn(['assigneeIdentifier' => '']);

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('  ');

        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService->expects(static::never())->method('createTask');

        $service = $this->createService(
            positionRepository: $this->createNoOpPositionRepository(),
            taskService: $taskService,
            ruleResolver: $ruleResolver,
            systemConfigService: $systemConfig,
        );

        $this->expectException(AdditionalDeliveryAssigneeMissingException::class);
        $this->expectExceptionMessage('No assignee available for additional delivery request task. Configure an active assignment rule or set LieferzeitenAdmin.config.defaultAssigneeLieferterminAnfrageZusaetzlich.');
        $service->createAdditionalDeliveryRequest($positionId, 'manual', Context::createDefaultContext());
    }

    /** @return EntityRepository&MockObject */
    private function createPositionRepositoryMock(): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('upsert')->willReturnCallback(function (array $payloads): void {
            foreach ($payloads as $payload) {
                $updatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
                $this->connection->executeStatement(
                    'UPDATE lieferzeiten_position
                     SET comment = :comment,
                         current_comment = :currentComment,
                         last_changed_by = :lastChangedBy,
                         last_changed_at = :lastChangedAt,
                         updated_at = :updatedAt
                     WHERE id = :id',
                    [
                        'comment' => $payload['comment'] ?? null,
                        'currentComment' => $payload['currentComment'] ?? null,
                        'lastChangedBy' => $payload['lastChangedBy'] ?? null,
                        'lastChangedAt' => ($payload['lastChangedAt'] ?? new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                        'updatedAt' => $updatedAt,
                        'id' => hex2bin((string) $payload['id']),
                    ],
                );
            }
        });

        return $repository;
    }

    /** @return EntityRepository&MockObject */
    private function createNoOpPositionRepository(): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('upsert')->willReturn(null);

        return $repository;
    }

    private function createService(
        ?EntityRepository $positionRepository = null,
        ?LieferzeitenTaskService $taskService = null,
        ?TaskAssignmentRuleResolver $ruleResolver = null,
        ?SystemConfigService $systemConfigService = null,
    ): LieferzeitenPositionWriteService {
        return new LieferzeitenPositionWriteService(
            $positionRepository ?? $this->createPositionRepositoryMock(),
            $this->createMock(EntityRepository::class),
            $this->connection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $taskService ?? $this->createMock(LieferzeitenTaskService::class),
            $this->createMock(NotificationEventService::class),
            $ruleResolver ?? $this->createMock(TaskAssignmentRuleResolver::class),
            $systemConfigService ?? $this->createMock(SystemConfigService::class),
        );
    }
}
