<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\PdmsLieferzeitenClient;
use LieferzeitenAdmin\Service\PdmsLieferzeitenService;
use PHPUnit\Framework\TestCase;

class PdmsLieferzeitenServiceTest extends TestCase
{
    public function testNormalizesAndLimitsToFourEntries(): void
    {
        $client = $this->createMock(PdmsLieferzeitenClient::class);
        $client->method('fetchLieferzeiten')->willReturn([
            ['uuid' => 'a', 'label' => '1-2 Tage', 'min_days' => '1', 'max_days' => '2'],
            ['id' => 'b', 'name' => '2-4 Tage', 'minDays' => 2, 'maxDays' => 4],
            ['code' => 'c', 'title' => '4-6 Tage', 'from' => 4, 'to' => 6],
            ['key' => 'd', 'deliveryTime' => '6-8 Tage', 'minDays' => '6', 'maxDays' => '8'],
            ['id' => 'e', 'name' => '8-10 Tage', 'minDays' => 8, 'maxDays' => 10],
        ]);

        $service = new PdmsLieferzeitenService($client);
        $result = $service->getNormalizedLieferzeiten();

        static::assertCount(4, $result);
        static::assertSame('a', $result[0]['id']);
        static::assertSame('1-2 Tage', $result[0]['name']);
        static::assertSame(1, $result[0]['minDays']);
        static::assertSame(2, $result[0]['maxDays']);

        static::assertSame('d', $result[3]['id']);
        static::assertSame('6-8 Tage', $result[3]['name']);
    }
}
