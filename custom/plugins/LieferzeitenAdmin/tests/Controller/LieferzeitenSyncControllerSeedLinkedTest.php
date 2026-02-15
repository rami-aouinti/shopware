<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Controller;

use ExternalOrders\Service\ExternalOrderTestDataService;
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

class LieferzeitenSyncControllerSeedLinkedTest extends TestCase
{
    public function testSeedLinkedDemoDataReturnsCleanupTransparencyFields(): void
    {
        $externalOrderIds = ['DEMO-1', 'DEMO-2'];

        $externalOrderTestDataService = $this->createMock(ExternalOrderTestDataService::class);
        $externalOrderTestDataService->method('removeSeededFakeOrders')->willReturn(3);
        $externalOrderTestDataService->method('seedFakeOrdersOnce')->willReturn(2);
        $externalOrderTestDataService->method('getDemoExternalOrderIds')->willReturn($externalOrderIds);

        $demoDataSeederService = $this->createMock(DemoDataSeederService::class);
        $demoDataSeederService->method('seed')->willReturn([
            'created' => ['paket' => 2, 'position' => 5],
        ]);
        $demoDataSeederService->expects($this->once())
            ->method('linkExpectedDemoExternalOrders')
            ->with($externalOrderIds, $this->isType('string'), $this->isType('string'), true)
            ->willReturn([
                'linked' => 1,
                'missingIds' => ['DEMO-2'],
                'deletedCount' => 1,
                'destructiveCleanup' => true,
            ]);

        $controller = new LieferzeitenSyncController(
            $this->createMock(LieferzeitenImportService::class),
            $this->createMock(TrackingHistoryService::class),
            $this->createMock(TrackingDeliveryDateSyncService::class),
            $this->createMock(LieferzeitenOrderOverviewService::class),
            $this->createMock(LieferzeitenPositionWriteService::class),
            $this->createMock(LieferzeitenTaskService::class),
            $this->createMock(LieferzeitenOrderStatusWriteService::class),
            $this->createMock(LieferzeitenStatisticsService::class),
            $demoDataSeederService,
            $externalOrderTestDataService,
            $this->createMock(AuditLogService::class),
            $this->createMock(PdmsLieferzeitenMappingService::class),
        );

        $response = $controller->seedLinkedDemoData(Context::createDefaultContext());
        $payload = json_decode((string) $response->getContent(), true);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(true, $payload['destructiveCleanup']);
        static::assertSame(1, $payload['deletedCount']);
        static::assertSame(['DEMO-2'], $payload['missingIds']);
    }
}
