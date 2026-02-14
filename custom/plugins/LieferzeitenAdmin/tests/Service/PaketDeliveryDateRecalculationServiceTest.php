<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Entity\PaketEntity;
use LieferzeitenAdmin\Service\BaseDateResolver;
use LieferzeitenAdmin\Service\BusinessDayDeliveryDateCalculator;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\PaketDeliveryDateRecalculationService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class PaketDeliveryDateRecalculationServiceTest extends TestCase
{
    public function testRecalculateByIdsUsesPaymentDateForPrepaymentWhenAvailable(): void
    {
        $context = Context::createDefaultContext();
        $entity = new PaketEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setSourceSystem('shopware');
        $entity->setPaymentMethod('Vorkasse');
        $entity->setOrderDate(new \DateTimeImmutable('2026-02-02 09:00:00'));
        $entity->setPaymentDate(new \DateTimeImmutable('2026-02-05 10:00:00'));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($entity));
        $repository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(static function (array $payload): bool {
                $update = $payload[0] ?? [];

                return ($update['baseDateType'] ?? null) === 'payment_date'
                    && ($update['shippingDate'] ?? null) === '2026-02-06 10:00:00'
                    && ($update['deliveryDate'] ?? null) === '2026-02-10 10:00:00'
                    && ($update['calculatedDeliveryDate'] ?? null) === '2026-02-10 10:00:00';
            }), $context);

        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->with('shopware')->willReturn([
            'shipping' => 1,
            'delivery' => 3,
        ]);

        $service = new PaketDeliveryDateRecalculationService(
            $repository,
            new BaseDateResolver(),
            $settingsProvider,
            new BusinessDayDeliveryDateCalculator(),
        );

        $service->recalculateByIds([$entity->getId()], $context);
    }

    public function testRecalculateByIdsFallsBackToOrderDateForPrepaymentWithoutPaymentDate(): void
    {
        $context = Context::createDefaultContext();
        $entity = new PaketEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setSourceSystem('shopware');
        $entity->setPaymentMethod('prepayment');
        $entity->setOrderDate(new \DateTimeImmutable('2026-02-02 09:00:00'));
        $entity->setPaymentDate(null);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($entity));
        $repository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(static function (array $payload): bool {
                $update = $payload[0] ?? [];

                return ($update['baseDateType'] ?? null) === 'order_date_fallback'
                    && ($update['shippingDate'] ?? null) === '2026-02-03 09:00:00'
                    && ($update['deliveryDate'] ?? null) === '2026-02-04 09:00:00'
                    && ($update['calculatedDeliveryDate'] ?? null) === '2026-02-04 09:00:00';
            }), $context);

        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->with('shopware')->willReturn([
            'shipping' => 1,
            'delivery' => 2,
        ]);

        $service = new PaketDeliveryDateRecalculationService(
            $repository,
            new BaseDateResolver(),
            $settingsProvider,
            new BusinessDayDeliveryDateCalculator(),
        );

        $service->recalculateByIds([$entity->getId()], $context);
    }

    private function createSearchResult(PaketEntity $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            'lieferzeiten_paket',
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
