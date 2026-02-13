<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\LieferzeitenStatisticsService;
use PHPUnit\Framework\TestCase;

class LieferzeitenStatisticsServiceTest extends TestCase
{
    public function testGetStatisticsPrefersChannelOverDomainForSelection(): void
    {
        $capturedParams = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams[] = $params;

                return ['open_orders' => 0, 'overdue_shipping' => 0, 'overdue_delivery' => 0];
            });
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams[] = $params;

                return [];
            });

        $service = new LieferzeitenStatisticsService($connection);
        $service->getStatistics(7, 'first-medical', 'shopware');

        static::assertNotEmpty($capturedParams);
        foreach ($capturedParams as $params) {
            static::assertSame('shopware', $params['sourceSystem'] ?? null);
        }
    }

    public function testGetStatisticsUsesDomainWhenChannelIsAll(): void
    {
        $capturedParams = [];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams[] = $params;

                return ['open_orders' => 0, 'overdue_shipping' => 0, 'overdue_delivery' => 0];
            });
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams[] = $params;

                return [];
            });

        $service = new LieferzeitenStatisticsService($connection);
        $service->getStatistics(30, 'first-medical', 'all');

        static::assertNotEmpty($capturedParams);
        foreach ($capturedParams as $params) {
            static::assertSame('first-medical', $params['sourceSystem'] ?? null);
        }
    }

    public function testGetStatisticsSanitizesInvalidPeriodToThirtyDays(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'open_orders' => 0,
            'overdue_shipping' => 0,
            'overdue_delivery' => 0,
        ]);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $service = new LieferzeitenStatisticsService($connection);
        $result = $service->getStatistics(999, null, null);

        static::assertSame(30, $result['periodDays']);
    }
}
