<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Sync\San6\San6MatchingService;
use PHPUnit\Framework\TestCase;

class San6MatchingServiceTest extends TestCase
{
    public function testMatchNormalizesSan6ParcelsIntoDedicatedRows(): void
    {
        $service = new San6MatchingService();

        $matched = $service->match([
            'orderNumber' => 'SO-10',
            'customerEmail' => 'buyer@example.org',
        ], [
            'shippingDate' => '2026-02-10',
            'parcels' => [
                ['packageNumber' => 'PK-1', 'sendenummer' => 'TR-1', 'state' => 'in_transit'],
                ['packageNumber' => 'PK-2', 'trackingNumber' => 'TR-2', 'status' => 'delivered'],
            ],
        ]);

        static::assertCount(2, $matched['parcelRows']);
        static::assertSame('PK-1', $matched['parcelRows'][0]['paketNumber']);
        static::assertSame('TR-1', $matched['parcelRows'][0]['trackingNumber']);
        static::assertSame('in_transit', $matched['parcelRows'][0]['status']);
        static::assertSame('PK-2', $matched['parcelRows'][1]['paketNumber']);
    }
}
