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

class LieferzeitenOrderOverviewApiIntegrationTest extends TestCase
{
    public function testOrdersEndpointReturnsSummaryWithoutDetailPayload(): void
    {
        $overviewService = $this->createMock(LieferzeitenOrderOverviewService::class);
        $overviewService->expects($this->once())
            ->method('listOrders')
            ->with(
                1,
                25,
                null,
                null,
                $this->isType('array'),
            )
            ->willReturn([
                'total' => 1,
                'page' => 1,
                'limit' => 25,
                'data' => [[
                    'id' => 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1',
                    'orderNumber' => 'SO-1000',
                ]],
            ]);

        $controller = $this->buildController($overviewService);

        $response = $controller->orders(new Request(), Context::createDefaultContext());

        static::assertSame(200, $response->getStatusCode());
        static::assertStringContainsString('SO-1000', (string) $response->getContent());
    }

    public function testOrderDetailsEndpointReturnsStructuredPayload(): void
    {
        $paketId = 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1';
        $overviewService = $this->createMock(LieferzeitenOrderOverviewService::class);
        $overviewService->expects($this->once())
            ->method('getOrderDetails')
            ->with($paketId)
            ->willReturn([
                'id' => $paketId,
                'positions' => [],
                'parcels' => [],
                'lieferterminLieferantHistory' => [],
                'neuerLieferterminHistory' => [],
                'commentHistory' => [],
            ]);

        $controller = $this->buildController($overviewService);

        $response = $controller->orderDetails($paketId, Context::createDefaultContext());

        static::assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        static::assertStringContainsString('"positions":[]', $content);
        static::assertStringContainsString('"parcels":[]', $content);
        static::assertStringContainsString('"lieferterminLieferantHistory":[]', $content);
        static::assertStringContainsString('"neuerLieferterminHistory":[]', $content);
        static::assertStringContainsString('"commentHistory":[]', $content);
    }

    private function buildController(LieferzeitenOrderOverviewService $overviewService): LieferzeitenSyncController
    {
        return new LieferzeitenSyncController(
            $this->createMock(LieferzeitenImportService::class),
            $this->createMock(TrackingHistoryService::class),
            $this->createMock(TrackingDeliveryDateSyncService::class),
            $overviewService,
            $this->createMock(LieferzeitenPositionWriteService::class),
            $this->createMock(LieferzeitenTaskService::class),
            $this->createMock(LieferzeitenOrderStatusWriteService::class),
            $this->createMock(LieferzeitenStatisticsService::class),
            $this->createMock(DemoDataSeederService::class),
            $this->createMock(AuditLogService::class),
            $this->createMock(PdmsLieferzeitenMappingService::class),
        );
    }
}
