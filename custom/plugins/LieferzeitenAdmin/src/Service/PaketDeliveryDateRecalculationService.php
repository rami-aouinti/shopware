<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PaketDeliveryDateRecalculationService
{
    public function __construct(
        private readonly EntityRepository $paketRepository,
        private readonly BaseDateResolver $baseDateResolver,
        private readonly ChannelDateSettingsProvider $settingsProvider,
        private readonly BusinessDayDeliveryDateCalculator $calculator,
    ) {
    }

    /** @param array<int,string> $ids */
    public function recalculateByIds(array $ids, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $criteria = new Criteria($ids);
        $pakete = $this->paketRepository->search($criteria, $context)->getEntities();

        $updates = [];
        foreach ($pakete as $paket) {
            $payload = [
                'orderDate' => $paket->get('orderDate')?->format(DATE_ATOM),
                'paymentDate' => $paket->get('paymentDate')?->format(DATE_ATOM),
                'paymentMethod' => $paket->get('paymentMethod'),
            ];

            $resolution = $this->baseDateResolver->resolve($payload);
            $update = [
                'id' => $paket->getId(),
                'baseDateType' => $resolution['baseDateType'],
                'calculatedDeliveryDate' => null,
            ];

            if ($resolution['baseDate'] !== null) {
                $settings = $this->settingsProvider->getForChannel((string) ($paket->get('sourceSystem') ?? 'shopware'));
                $calculatedShippingDate = $this->calculator->calculate($resolution['baseDate'], $settings['shipping']);
                $calculatedDeliveryDate = $this->calculator->calculate($resolution['baseDate'], $settings['delivery']);

                $update['shippingDate'] = $calculatedShippingDate?->format('Y-m-d H:i:s');
                $update['deliveryDate'] = $calculatedDeliveryDate?->format('Y-m-d H:i:s');
                $update['calculatedDeliveryDate'] = $calculatedDeliveryDate?->format('Y-m-d H:i:s');
            }

            $updates[] = $update;
        }

        if ($updates !== []) {
            $this->paketRepository->upsert($updates, $context);
        }
    }

    public function recalculateForSourceSystem(string $sourceSystem, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sourceSystem', $sourceSystem));
        $ids = $this->paketRepository->searchIds($criteria, $context)->getIds();

        $this->recalculateByIds($ids, $context);
    }
}
