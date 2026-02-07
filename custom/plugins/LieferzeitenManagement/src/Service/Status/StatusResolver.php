<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Status;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class StatusResolver
{
    public const SOURCE_SHOPWARE = 'shopware';
    public const SOURCE_SAN6 = 'san6';
    public const SOURCE_TRACKING_INTERNAL = 'tracking_internal';

    public const STATUS_PREPAYMENT = 1;
    public const STATUS_PAID = 2;
    public const STATUS_IN_PROGRESS = 3;
    public const STATUS_CANCELLED = 4;
    public const STATUS_SHIPPED = 5;
    public const STATUS_COMPLETED = 6;
    public const STATUS_SAN6 = 7;
    public const STATUS_TRACKING_INTERNAL = 8;

    /**
     * @return array{code:int,label:string,source:string}
     */
    public function resolveForSource(string $source): array
    {
        if ($source === self::SOURCE_TRACKING_INTERNAL) {
            return $this->buildStatus(self::STATUS_TRACKING_INTERNAL, 'Tracking/San6 intern', $source);
        }

        if ($source === self::SOURCE_SAN6) {
            return $this->buildStatus(self::STATUS_SAN6, 'San6', $source);
        }

        return $this->buildStatus(self::STATUS_IN_PROGRESS, 'In Bearbeitung', self::SOURCE_SHOPWARE);
    }

    /**
     * @return array{code:int,label:string,source:string}
     */
    public function resolveForOrder(OrderEntity $order): array
    {
        $orderState = $order->getStateMachineState();
        $deliveryState = $order->getDeliveries()?->first()?->getStateMachineState();
        $transactionState = $order->getTransactions()?->last()?->getStateMachineState();
        $paymentMethod = $order->getTransactions()?->last()?->getPaymentMethod()?->getTechnicalName();

        if ($this->hasState($orderState, OrderStates::STATE_COMPLETED)) {
            return $this->buildStatus(self::STATUS_COMPLETED, 'Bestellung abgeschlossen', self::SOURCE_SHOPWARE);
        }

        if ($this->hasState($deliveryState, 'shipped')) {
            return $this->buildStatus(self::STATUS_SHIPPED, 'Versendet', self::SOURCE_SHOPWARE);
        }

        if ($this->hasState($transactionState, 'paid') || $this->hasState($transactionState, 'paid_partially')) {
            return $this->buildStatus(self::STATUS_PAID, 'Bezahlt', self::SOURCE_SHOPWARE);
        }

        if ($this->hasState($transactionState, 'open') || $this->hasState($transactionState, 'authorized')
            || ($paymentMethod !== null && str_contains($paymentMethod, 'prepayment'))
        ) {
            return $this->buildStatus(self::STATUS_PREPAYMENT, 'Vorkasse', self::SOURCE_SHOPWARE);
        }

        if ($this->hasState($orderState, OrderStates::STATE_CANCELLED)) {
            return $this->buildStatus(self::STATUS_CANCELLED, 'Storniert', self::SOURCE_SHOPWARE);
        }

        return $this->buildStatus(self::STATUS_IN_PROGRESS, 'In Bearbeitung', self::SOURCE_SHOPWARE);
    }

    /**
     * @return array{code:int,label:string,source:string}
     */
    private function buildStatus(int $code, string $label, string $source): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'source' => $source,
        ];
    }

    private function hasState(?StateMachineStateEntity $state, string $technicalName): bool
    {
        return $state !== null && $state->getTechnicalName() === $technicalName;
    }
}
