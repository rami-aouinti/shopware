<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Controller;

use Doctrine\DBAL\Connection;
use ExternalOrders\Controller\ExternalOrderController;
use ExternalOrders\Service\ExternalOrderService;
use ExternalOrders\Service\ExternalOrderSyncService;
use ExternalOrders\Service\ExternalOrderTestDataService;
use ExternalOrders\Service\TopmSan6OrderExportService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ExternalOrderControllerTest extends TestCase
{
    public function testDetailAndExportRoutesUseInternalOrderIdPlaceholder(): void
    {
        $reflection = new \ReflectionClass(ExternalOrderController::class);

        $detailRoute = $reflection->getMethod('detail')->getAttributes(Route::class)[0]->getArguments();
        static::assertSame('/api/_action/external-orders/detail/{internalOrderId}', $detailRoute['path'] ?? null);

        $exportRoute = $reflection->getMethod('exportOrder')->getAttributes(Route::class)[0]->getArguments();
        static::assertSame('/api/_action/external-orders/export/{internalOrderId}', $exportRoute['path'] ?? null);

        $exportStatusRoute = $reflection->getMethod('exportStatus')->getAttributes(Route::class)[0]->getArguments();
        static::assertSame('/api/_action/external-orders/export-status/{internalOrderId}', $exportStatusRoute['path'] ?? null);
    }

    public function testMarkTestPrefersInternalOrderIdsPayload(): void
    {
        $externalOrderService = $this->createMock(ExternalOrderService::class);
        $externalOrderService
            ->expects($this->once())
            ->method('markOrdersAsTest')
            ->with($this->isInstanceOf(Context::class), ['internal-1'])
            ->willReturn(['updated' => 1, 'alreadyMarked' => 0, 'notFound' => 0]);

        $controller = new ExternalOrderController(
            $externalOrderService,
            $this->createMock(ExternalOrderTestDataService::class),
            $this->createMock(ExternalOrderSyncService::class),
            $this->createMock(TopmSan6OrderExportService::class),
            $this->createMock(Connection::class),
        );

        $request = new Request(content: json_encode([
            'orderIds' => ['legacy-id'],
            'internalOrderIds' => ['internal-1'],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->markTest($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"updated":1,"alreadyMarked":0,"notFound":0}', $response->getContent());
    }

    public function testFileTransferRouteIsExplicitlyPublicWithoutAcl(): void
    {
        $reflection = new \ReflectionClass(ExternalOrderController::class);
        $method = $reflection->getMethod('serveTopmExportFile');
        $attributes = $method->getAttributes(Route::class);

        static::assertCount(1, $attributes);

        /** @var array{path?: string, defaults?: array{auth_required?: bool, _acl?: array<mixed>}, name?: string} $arguments */
        $arguments = $attributes[0]->getArguments();

        static::assertSame('/topm-export/{token}', $arguments['path'] ?? null);
        static::assertSame('api.external-orders.export.file-transfer', $arguments['name'] ?? null);
        static::assertFalse($arguments['defaults']['auth_required'] ?? true);
        static::assertSame([], $arguments['defaults']['_acl'] ?? ['unexpected']);
    }
}
