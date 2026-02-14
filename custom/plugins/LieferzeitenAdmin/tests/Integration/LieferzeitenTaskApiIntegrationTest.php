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

    private function buildController(
        LieferzeitenTaskService $taskService,
        ?LieferzeitenPositionWriteService $positionWriteService = null,
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
            $this->createMock(AuditLogService::class),
            $this->createMock(PdmsLieferzeitenMappingService::class),
        );
    }
}
