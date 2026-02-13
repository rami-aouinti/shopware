<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Controller;

use Doctrine\DBAL\Connection;
use ExternalOrders\Controller\ExternalOrderController;
use ExternalOrders\Service\ExternalOrderService;
use ExternalOrders\Service\ExternalOrderSyncService;
use ExternalOrders\Service\ExternalOrderTestDataService;
use ExternalOrders\Service\TopmSan6OrderExportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ExternalOrderControllerTest extends TestCase
{
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

    public function testServeTopmExportFileReturnsXmlWhenSignedTokenIsValid(): void
    {
        $controller = $this->createControllerWithExportResult('<export>ok</export>');

        $response = $controller->serveTopmExportFile('valid-token');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('application/xml; charset=utf-8', $response->headers->get('Content-Type'));
        static::assertSame('<export>ok</export>', $response->getContent());
    }

    public function testServeTopmExportFileReturnsNotFoundWhenTokenIsInvalidOrExpired(): void
    {
        $controller = $this->createControllerWithExportResult(null);

        $response = $controller->serveTopmExportFile('invalid-or-expired-token');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function createControllerWithExportResult(?string $xml): ExternalOrderController
    {
        $exportService = $this->createMock(TopmSan6OrderExportService::class);
        $exportService->method('serveSignedExportXml')->willReturn($xml);

        return new ExternalOrderController(
            $this->createMock(ExternalOrderService::class),
            $this->createMock(ExternalOrderTestDataService::class),
            $this->createMock(ExternalOrderSyncService::class),
            $exportService,
            $this->createMock(Connection::class),
        );
    }
}
