<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Tracking\TrackingClientInterface;
use LieferzeitenAdmin\Service\Tracking\TrackingHistoryService;
use LieferzeitenAdmin\Service\Tracking\TrackingProviderException;
use PHPUnit\Framework\TestCase;

class TrackingHistoryServiceTest extends TestCase
{
    public function testFetchHistoryReturnsNormalizedSortedEventsForTrackingPopup(): void
    {
        $client = $this->createMock(TrackingClientInterface::class);
        $client->method('supportsCarrier')->willReturn(true);
        $client->method('fetchHistory')->willReturn([
            ['status' => 'in_transit', 'label' => 'Unterwegs', 'timestamp' => '2026-02-01T10:00:00+00:00', 'location' => 'DE'],
            ['status' => 'zugestellt', 'label' => 'Zugestellt', 'timestamp' => '2026-02-02T10:00:00+00:00', 'location' => 'DE'],
        ]);

        $service = new TrackingHistoryService([$client]);

        $response = $service->fetchHistory('dhl', '0034043412345678');

        static::assertTrue($response['ok']);
        static::assertSame('DHL', $response['carrier']);
        static::assertSame('delivered', $response['events'][0]['status']);
        static::assertSame('in_transit', $response['events'][1]['status']);
    }

    public function testFetchHistoryReturnsProviderErrorPayload(): void
    {
        $client = $this->createMock(TrackingClientInterface::class);
        $client->method('supportsCarrier')->willReturn(true);
        $client->method('fetchHistory')->willThrowException(new TrackingProviderException('invalid_tracking_number', 'Invalid tracking number'));

        $service = new TrackingHistoryService([$client]);

        $response = $service->fetchHistory('gls', 'invalid');

        static::assertFalse($response['ok']);
        static::assertSame('invalid_tracking_number', $response['errorCode']);
        static::assertSame('GLS', $response['carrier']);
    }

    public function testFetchHistoryReturnsCarrierNotSupportedError(): void
    {
        $client = $this->createMock(TrackingClientInterface::class);
        $client->method('supportsCarrier')->willReturn(false);

        $service = new TrackingHistoryService([$client]);

        $response = $service->fetchHistory('dpd', '123');

        static::assertFalse($response['ok']);
        static::assertSame('carrier_not_supported', $response['errorCode']);
    }
}
