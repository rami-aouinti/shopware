<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Controller;

use LieferzeitenAdmin\Controller\LieferzeitenSyncController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

class LieferzeitenSyncControllerAclTest extends TestCase
{
    public function testReadRoutesUseViewerAndWriteRoutesUseEditor(): void
    {
        $reflection = new \ReflectionClass(LieferzeitenSyncController::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                /** @var array{path?: string, methods?: list<string>, defaults?: array{_acl?: list<string>}} $arguments */
                $arguments = $attribute->getArguments();
                $path = (string) ($arguments['path'] ?? '');

                if (!str_starts_with($path, '/api/_action/lieferzeiten')) {
                    continue;
                }

                $httpMethods = $arguments['methods'] ?? [];
                $acl = $arguments['defaults']['_acl'] ?? [];

                static::assertCount(1, $acl, sprintf('Route %s (%s) must expose exactly one ACL role', $path, $method->getName()));

                if ($httpMethods === ['GET']) {
                    static::assertSame(['lieferzeiten.viewer'], $acl, sprintf('Read route %s must require lieferzeiten.viewer', $path));
                    continue;
                }

                static::assertSame(['lieferzeiten.editor'], $acl, sprintf('Write route %s must require lieferzeiten.editor', $path));
            }
        }
    }

    #[DataProvider('forbiddenWhenMissingPermissionProvider')]
    public function testApiAclMatrixForForbiddenResponses(string $httpMethod, string $path, string $requiredPermission): void
    {
        $routeMap = $this->buildRoutePermissionMap();
        $key = sprintf('%s %s', $httpMethod, $path);

        static::assertArrayHasKey($key, $routeMap, sprintf('Route %s is missing in controller ACL mapping', $key));
        static::assertSame($requiredPermission, $routeMap[$key]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function forbiddenWhenMissingPermissionProvider(): iterable
    {
        yield 'read tasks requires viewer' => ['GET', '/api/_action/lieferzeiten/tasks', 'lieferzeiten.viewer'];
        yield 'read orders requires viewer' => ['GET', '/api/_action/lieferzeiten/orders', 'lieferzeiten.viewer'];
        yield 'read statistics requires viewer' => ['GET', '/api/_action/lieferzeiten/statistics', 'lieferzeiten.viewer'];
        yield 'read tracking requires viewer' => ['GET', '/api/_action/lieferzeiten/tracking/{carrier}/{trackingNumber}', 'lieferzeiten.viewer'];
        yield 'read demo data status requires viewer' => ['GET', '/api/_action/lieferzeiten/demo-data/status', 'lieferzeiten.viewer'];
        yield 'read sales channel lieferzeiten requires viewer' => ['GET', '/api/_action/lieferzeiten/sales-channel/{salesChannelId}/lieferzeiten', 'lieferzeiten.viewer'];

        yield 'assign task requires editor' => ['POST', '/api/_action/lieferzeiten/tasks/{taskId}/assign', 'lieferzeiten.editor'];
        yield 'close task requires editor' => ['POST', '/api/_action/lieferzeiten/tasks/{taskId}/close', 'lieferzeiten.editor'];
        yield 'reopen task requires editor' => ['POST', '/api/_action/lieferzeiten/tasks/{taskId}/reopen', 'lieferzeiten.editor'];
        yield 'cancel task requires editor' => ['POST', '/api/_action/lieferzeiten/tasks/{taskId}/cancel', 'lieferzeiten.editor'];
        yield 'sync import requires editor' => ['POST', '/api/_action/lieferzeiten/sync', 'lieferzeiten.editor'];
        yield 'seed demo data requires editor' => ['POST', '/api/_action/lieferzeiten/demo-data', 'lieferzeiten.editor'];
        yield 'toggle demo data requires editor' => ['POST', '/api/_action/lieferzeiten/demo-data/toggle', 'lieferzeiten.editor'];
        yield 'update supplier date requires editor' => ['POST', '/api/_action/lieferzeiten/position/{positionId}/liefertermin-lieferant', 'lieferzeiten.editor'];
        yield 'update new date requires editor' => ['POST', '/api/_action/lieferzeiten/position/{positionId}/neuer-liefertermin', 'lieferzeiten.editor'];
        yield 'update new date by paket requires editor' => ['POST', '/api/_action/lieferzeiten/paket/{paketId}/neuer-liefertermin', 'lieferzeiten.editor'];
        yield 'update comment requires editor' => ['POST', '/api/_action/lieferzeiten/position/{positionId}/comment', 'lieferzeiten.editor'];
        yield 'create additional request requires editor' => ['POST', '/api/_action/lieferzeiten/position/{positionId}/additional-delivery-request', 'lieferzeiten.editor'];
    }

    /**
     * @return array<string, string>
     */
    private function buildRoutePermissionMap(): array
    {
        $map = [];
        $reflection = new \ReflectionClass(LieferzeitenSyncController::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                /** @var array{path?: string, methods?: list<string>, defaults?: array{_acl?: list<string>}} $arguments */
                $arguments = $attribute->getArguments();
                $path = (string) ($arguments['path'] ?? '');

                if (!str_starts_with($path, '/api/_action/lieferzeiten')) {
                    continue;
                }

                foreach ($arguments['methods'] ?? [] as $httpMethod) {
                    $permission = $arguments['defaults']['_acl'][0] ?? '';
                    $map[sprintf('%s %s', $httpMethod, $path)] = $permission;
                }
            }
        }

        return $map;
    }
}
