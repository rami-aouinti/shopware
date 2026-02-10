<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Integration;

use LieferzeitenAdmin\Controller\LieferzeitenSyncController;
use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use LieferzeitenAdmin\Service\LieferzeitenPositionWriteService;
use LieferzeitenAdmin\Service\LieferzeitenStatisticsService;
use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\Tracking\TrackingHistoryService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

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

    private function buildController(LieferzeitenTaskService $taskService): LieferzeitenSyncController
    {
        return new LieferzeitenSyncController(
            $this->createMock(LieferzeitenImportService::class),
            $this->createMock(TrackingHistoryService::class),
            $this->createMock(LieferzeitenOrderOverviewService::class),
            $this->createMock(LieferzeitenPositionWriteService::class),
            $taskService,
            $this->createMock(LieferzeitenStatisticsService::class),
            $this->createMock(AuditLogService::class),
        );
    }
}
