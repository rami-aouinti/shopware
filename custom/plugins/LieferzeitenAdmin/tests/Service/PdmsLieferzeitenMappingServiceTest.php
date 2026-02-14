<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\PdmsLieferzeitenService;
use LieferzeitenAdmin\Service\PdmsLieferzeitenMappingService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class PdmsLieferzeitenMappingServiceTest extends TestCase
{
    public function testMapsFourSlotsUsingSalesChannelMappingAndFallback(): void
    {
        $salesChannelId = '018f4f79a07f7a1f8f66f5af4823fcb1';
        $context = Context::createDefaultContext();

        $pdmsService = $this->createMock(PdmsLieferzeitenService::class);
        $pdmsService->method('getNormalizedLieferzeiten')->willReturn([
            ['id' => 'lz-a', 'name' => '1-2', 'minDays' => 1, 'maxDays' => 2],
            ['id' => 'lz-b', 'name' => '2-4', 'minDays' => 2, 'maxDays' => 4],
            ['id' => 'lz-c', 'name' => '4-6', 'minDays' => 4, 'maxDays' => 6],
            ['id' => 'lz-d', 'name' => '6-8', 'minDays' => 6, 'maxDays' => 8],
        ]);

        $salesChannelEntity = new class () extends Entity {
            protected string $name = 'Storefront';
            protected array $customFields = [
                'pdms_lieferzeiten_mapping' => '{"1":"lz-b","3":"lz-d"}',
            ];
        };
        $salesChannelEntity->assign(['id' => $salesChannelId]);

        $searchResult = new EntitySearchResult(
            'sales_channel',
            1,
            new EntityCollection([$salesChannelEntity]),
            null,
            new Criteria([$salesChannelId]),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $service = new PdmsLieferzeitenMappingService($pdmsService, $repository);

        $result = $service->getForSalesChannel($salesChannelId, $context);

        static::assertSame($salesChannelId, $result['salesChannelId']);
        static::assertSame('Storefront', $result['salesChannelName']);
        static::assertCount(4, $result['lieferzeiten']);
        static::assertSame('lz-b', $result['lieferzeiten'][0]['lieferzeit']['id']);
        static::assertSame('lz-b', $result['lieferzeiten'][1]['lieferzeit']['id']);
        static::assertSame('lz-d', $result['lieferzeiten'][2]['lieferzeit']['id']);
        static::assertSame('lz-d', $result['lieferzeiten'][3]['lieferzeit']['id']);
    }
}
