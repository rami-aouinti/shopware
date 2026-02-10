<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\BaseDateResolver;
use LieferzeitenAdmin\Service\BusinessDayDeliveryDateCalculator;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Sync\Adapter\ChannelOrderAdapterRegistry;
use LieferzeitenAdmin\Sync\San6\San6Client;
use LieferzeitenAdmin\Sync\San6\San6MatchingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LieferzeitenImportServiceTest extends TestCase
{
    public function testIsTestOrderRecognizesTestbestellungFlag(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isTestOrder');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [
            'detail' => [
                'additional' => [
                    'Testbestellung' => '1',
                ],
            ],
        ]));
    }

    public function testIsTestOrderKeepsRegularOrders(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isTestOrder');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, [
            'orderNumber' => 'SO-10001',
            'customerEmail' => 'buyer@example.org',
            'isTestOrder' => false,
        ]));
    }

    public function testIsCompletedStatus8UsesSan6TrackingMapping(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'paketshop_retire'],
                ['trackingStatus' => 'ablageort'],
                ['trackingStatus' => 'zugestellt'],
            ],
        ], []));

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'paketshop_non_retire'],
                ['trackingStatus' => 'zugestellt'],
            ],
        ], []));

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'retoure'],
            ],
        ], []));
    }

    public function testIsCompletedStatus8RequiresAllParcelsToBeClosed(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'zugestellt'],
                ['trackingStatus' => 'nicht_zustellbar'],
            ],
        ], []));
    }

    private function createService(): LieferzeitenImportService
    {
        return new LieferzeitenImportService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(ChannelOrderAdapterRegistry::class),
            $this->createMock(San6Client::class),
            $this->createMock(San6MatchingService::class),
            $this->createMock(BaseDateResolver::class),
            $this->createMock(ChannelDateSettingsProvider::class),
            $this->createMock(BusinessDayDeliveryDateCalculator::class),
            $this->createMock(LockFactory::class),
            $this->createMock(NotificationEventService::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}
