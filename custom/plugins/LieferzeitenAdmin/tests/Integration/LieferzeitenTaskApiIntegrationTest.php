<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Integration;

use LieferzeitenAdmin\Controller\LieferzeitenSyncController;
use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\DemoDataSeederService;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use LieferzeitenAdmin\Service\LieferzeitenOrderStatusWriteService;
use LieferzeitenAdmin\Service\LieferzeitenPositionWriteService;
use LieferzeitenAdmin\Service\LieferzeitenStatisticsService;
use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\PdmsLieferzeitenMappingService;
use LieferzeitenAdmin\Service\Tracking\TrackingDeliveryDateSyncService;
use LieferzeitenAdmin\Service\Tracking\TrackingHistoryService;
use LieferzeitenAdmin\Service\Notification\TaskAssignmentRuleResolver;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

class LieferzeitenTaskApiIntegrationTest extends TestCase
{
    public function testReopenTaskEndpointReturnsBadRequestForInvalidId(): void
    {
        $controller = $this->buildController($this->createMock(LieferzeitenTaskService::class));

        $response = $controller->reopenTask('invalid', Context::createDefaultContext());

        static::assertSame(400, $response->getStatusCode());
    }

    public function testCancelTaskEndpointCallsServiceForValidId(): void
    {
        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService->expects($this->once())->method('cancelTask');

        $controller = $this->buildController($taskService);
        $validId = 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1';

        $response = $controller->cancelTask($validId, Context::createDefaultContext());

        static::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateNeuerLieferterminByPaketEndpointCallsService(): void
    {
        $paketId = 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1';
        $updatedAt = '2026-02-14 12:00:00.000';
        $positionWriteService = $this->createMock(LieferzeitenPositionWriteService::class);

        $positionWriteService->expects($this->once())
            ->method('canUpdateNeuerLieferterminForPaket')
            ->with($paketId)
            ->willReturn(true);

        $positionWriteService->expects($this->once())
            ->method('getSupplierRangeBoundsByPaketId')
            ->with($paketId)
            ->willReturn([
                'from' => new \DateTimeImmutable('2026-03-10'),
                'to' => new \DateTimeImmutable('2026-03-20'),
            ]);

        $positionWriteService->expects($this->once())
            ->method('updateNeuerLieferterminByPaket')
            ->with(
                $paketId,
                new \DateTimeImmutable('2026-03-11'),
                new \DateTimeImmutable('2026-03-14'),
                $updatedAt,
                $this->isInstanceOf(Context::class),
            );

        $controller = $this->buildController(
            $this->createMock(LieferzeitenTaskService::class),
            $positionWriteService,
        );

        $request = new Request(content: json_encode([
            'updatedAt' => $updatedAt,
            'from' => '2026-03-11',
            'to' => '2026-03-14',
        ], \JSON_THROW_ON_ERROR));

        $response = $controller->updateNeuerLieferterminByPaket($paketId, $request, Context::createDefaultContext());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('{"status":"ok"}', $response->getContent());
    }


    public function testUpdateNeuerLieferterminByPaketEndpointReturnsBadRequestWhenStatusDoesNotAllowEdit(): void
    {
        $paketId = 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1';
        $positionWriteService = $this->createMock(LieferzeitenPositionWriteService::class);

        $positionWriteService->expects($this->once())
            ->method('canUpdateNeuerLieferterminForPaket')
            ->with($paketId)
            ->willReturn(false);

        $positionWriteService->expects($this->never())
            ->method('updateNeuerLieferterminByPaket');

        $controller = $this->buildController(
            $this->createMock(LieferzeitenTaskService::class),
            $positionWriteService,
        );

        $request = new Request(content: json_encode([
            'updatedAt' => '2026-02-14 12:00:00.000',
            'from' => '2026-03-11',
            'to' => '2026-03-14',
        ], \JSON_THROW_ON_ERROR));

        $response = $controller->updateNeuerLieferterminByPaket($paketId, $request, Context::createDefaultContext());

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('{"status":"error","message":"Paket status does not allow editing the new delivery date"}', $response->getContent());
    }

    public function testAdditionalDeliveryRequestEndpointUsesTechnicalFallbackAssigneeWhenRuleAndConfigAreMissing(): void
    {
        $positionId = 'd57dc94b0fcd4d748f1f89ff4ce4dc0f';

        $positionWriteService = $this->createPositionWriteServiceWithMissingAssigneeFallback($positionId);
        $auditLogService = $this->createMock(AuditLogService::class);
        $auditLogService->expects($this->once())->method('log');

        $controller = $this->buildController(
            $this->createMock(LieferzeitenTaskService::class),
            $positionWriteService,
            $auditLogService,
        );

        $request = new Request(content: json_encode([
            'initiator' => 'manual',
        ], \JSON_THROW_ON_ERROR));

        $response = $controller->createAdditionalDeliveryRequest($positionId, $request, Context::createDefaultContext());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('{"status":"ok"}', $response->getContent());
    }



    private function createPositionWriteServiceWithMissingAssigneeFallback(string $positionId): LieferzeitenPositionWriteService
    {
        $connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $connection->executeStatement('CREATE TABLE lieferzeiten_paket (id BLOB PRIMARY KEY, external_order_id TEXT NULL, source_system TEXT NULL, customer_email TEXT NULL)');
        $connection->executeStatement('CREATE TABLE lieferzeiten_position (id BLOB PRIMARY KEY, paket_id BLOB NULL, position_number TEXT NULL, updated_at TEXT NOT NULL)');
        $connection->insert('lieferzeiten_position', [
            'id' => hex2bin($positionId),
            'updated_at' => '2026-02-10 09:00:00.000',
        ]);

        $positionRepository = $this->createMock(EntityRepository::class);
        $positionRepository->method('upsert')->willReturn(null);

        $taskService = $this->createMock(LieferzeitenTaskService::class);
        $taskService->expects($this->once())
            ->method('createTask')
            ->with(
                $this->isType('array'),
                $this->isInstanceOf(Context::class),
                'manual',
                'system',
                $this->isInstanceOf(\DateTimeInterface::class),
            );

        $ruleResolver = $this->createMock(TaskAssignmentRuleResolver::class);
        $ruleResolver->method('resolve')->willReturn(['assigneeIdentifier' => '']);

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('  ');

        return new LieferzeitenPositionWriteService(
            $positionRepository,
            $this->createMock(EntityRepository::class),
            $connection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $taskService,
            $this->createMock(NotificationEventService::class),
            $ruleResolver,
            $systemConfig,
        );
    }

    private function buildController(
        LieferzeitenTaskService $taskService,
        ?LieferzeitenPositionWriteService $positionWriteService = null,
        ?AuditLogService $auditLogService = null,
    ): LieferzeitenSyncController {
        return new LieferzeitenSyncController(
            $this->createMock(LieferzeitenImportService::class),
            $this->createMock(TrackingHistoryService::class),
            $this->createMock(TrackingDeliveryDateSyncService::class),
            $this->createMock(LieferzeitenOrderOverviewService::class),
            $positionWriteService ?? $this->createMock(LieferzeitenPositionWriteService::class),
            $taskService,
            $this->createMock(LieferzeitenOrderStatusWriteService::class),
            $this->createMock(LieferzeitenStatisticsService::class),
            $this->createMock(DemoDataSeederService::class),
            $auditLogService ?? $this->createMock(AuditLogService::class),
            $this->createMock(PdmsLieferzeitenMappingService::class),
        );
    }
}
