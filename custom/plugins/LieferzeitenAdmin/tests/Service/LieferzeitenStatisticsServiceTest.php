<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
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

        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $settingsProvider);
        $service->getStatistics(7, 'first-medical', 'shopware');

        static::assertNotEmpty($capturedParams);
        foreach ($capturedParams as $params) {
            static::assertSame('shopware', $params['sourceSystem0'] ?? null);
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

        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $settingsProvider);
        $service->getStatistics(30, 'first-medical', 'all');

        static::assertNotEmpty($capturedParams);
        foreach ($capturedParams as $params) {
            static::assertSame('first-medical', $params['sourceSystem0'] ?? null);
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

        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $settingsProvider);
        $result = $service->getStatistics(999, null, null);

        static::assertSame(30, $result['periodDays']);
    }
}
