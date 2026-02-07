<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Deadline;

use LieferzeitenManagement\Core\Content\Settings\LieferzeitenSettingsDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DeadlineResolver
{
    /**
     * @param EntityRepository<OrderDefinition> $orderRepository
     * @param EntityRepository<LieferzeitenSettingsDefinition> $settingsRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $settingsRepository,
        private readonly DeadlineCalculator $deadlineCalculator
    ) {
    }

    /**
     * @return array{latestShippingAt?:\DateTimeImmutable,latestDeliveryAt?:\DateTimeImmutable}
     */
    public function resolveForOrder(string $orderId, Context $context): array
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            return [];
        }

        $baseDate = $order->getOrderDateTime();
        $paymentMethodName = null;
        $paidAt = null;

        foreach ($order->getTransactions() ?? [] as $transaction) {
            $paymentMethodName = $transaction->getPaymentMethod()?->getName();
            $paidAt = $transaction->getPaidAt();
            if ($paidAt) {
                break;
            }
        }

        if ($paymentMethodName && stripos($paymentMethodName, 'Vorkasse') !== false && $paidAt) {
            $baseDate = $paidAt;
        }

        $settings = $this->loadSettings($order->getSalesChannelId(), $context);
        $latestShippingOffset = $settings?->getLatestShippingOffsetDays() ?? 0;
        $latestDeliveryOffset = $settings?->getLatestDeliveryOffsetDays() ?? 0;
        $cutoffTime = $settings?->getCutoffTime();

        return [
            'latestShippingAt' => $this->deadlineCalculator->calculateLatestDate($baseDate, $latestShippingOffset, $cutoffTime),
            'latestDeliveryAt' => $this->deadlineCalculator->calculateLatestDate($baseDate, $latestDeliveryOffset, $cutoffTime),
        ];
    }

    private function loadSettings(?string $salesChannelId, Context $context): ?\LieferzeitenManagement\Core\Content\Settings\LieferzeitenSettingsEntity
    {
        $criteria = new Criteria();

        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        }

        $criteria->setLimit(1);

        return $this->settingsRepository->search($criteria, $context)->first();
    }
}
