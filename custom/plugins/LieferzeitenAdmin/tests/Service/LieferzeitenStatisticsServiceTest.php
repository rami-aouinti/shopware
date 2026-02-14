<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\ChannelPdmsThresholdResolver;
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

        $thresholdResolver = $this->createMock(ChannelPdmsThresholdResolver::class);
        $thresholdResolver->method('resolveForOrder')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $thresholdResolver);
        $service->getStatistics(7, 'first-medical', 'shopware', null, null);

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

        $thresholdResolver = $this->createMock(ChannelPdmsThresholdResolver::class);
        $thresholdResolver->method('resolveForOrder')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $thresholdResolver);
        $service->getStatistics(30, 'first-medical', 'all', null, null);

        static::assertNotEmpty($capturedParams);
        foreach ($capturedParams as $params) {
            static::assertSame('first-medical', $params['sourceSystem0'] ?? null);
        }
    }



    public function testGetStatisticsUsesCustomWindowWhenFromToProvided(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'open_orders' => 0,
            'overdue_shipping' => 0,
            'overdue_delivery' => 0,
        ]);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $thresholdResolver = $this->createMock(ChannelPdmsThresholdResolver::class);
        $thresholdResolver->method('resolveForOrder')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $thresholdResolver);
        $result = $service->getStatistics(30, null, null, '2026-02-01T00:00:00+01:00', '2026-02-05T23:59:59+01:00');

        static::assertSame('custom', $result['window']['mode']);
        static::assertSame('2026-02-01T00:00:00+01:00', $result['window']['from']);
        static::assertSame('2026-02-05T23:59:59+01:00', $result['window']['to']);
        static::assertSame('+01:00', $result['window']['timezone']);
        static::assertSame(5, $result['periodDays']);
    }

    public function testGetStatisticsFallsBackToPeriodWindow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'open_orders' => 0,
            'overdue_shipping' => 0,
            'overdue_delivery' => 0,
        ]);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $thresholdResolver = $this->createMock(ChannelPdmsThresholdResolver::class);
        $thresholdResolver->method('resolveForOrder')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $thresholdResolver);
        $result = $service->getStatistics(7, null, null, null, null);

        static::assertSame('period', $result['window']['mode']);
        static::assertSame(7, $result['periodDays']);
        static::assertArrayHasKey('from', $result['window']);
        static::assertArrayHasKey('to', $result['window']);
        static::assertArrayHasKey('timezone', $result['window']);
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

        $thresholdResolver = $this->createMock(ChannelPdmsThresholdResolver::class);
        $thresholdResolver->method('resolveForOrder')->willReturn([
            'shipping' => ['workingDays' => 0, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '14:00'],
        ]);

        $service = new LieferzeitenStatisticsService($connection, $thresholdResolver);
        $result = $service->getStatistics(999, null, null, null, null);

        static::assertSame(30, $result['periodDays']);
    }
}
