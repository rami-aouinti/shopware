<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\San6;

class San6MatchingService
{
    public const INTERNAL_DELIVERY_COMPLETED_FLAG = 'internalDeliveryCompleted';

    public const INTERNAL_DELIVERY_STATE = 'internalDeliveryStatus';

    public const INTERNAL_DELIVERY_COMPLETED_STATE = 'completed';

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $san6
     * @return array<string,mixed>
     */
    public function match(array $order, array $san6): array
    {
        if ($san6 === []) {
            return $order;
        }

        $conflicts = [];
        if (($order['customerEmail'] ?? null) !== null && ($san6['customer']['email'] ?? null) !== null) {
            if (mb_strtolower((string) $order['customerEmail']) !== mb_strtolower((string) $san6['customer']['email'])) {
                $conflicts[] = 'customer_email';
            }
        }

        if (($order['paymentMethod'] ?? null) !== null && ($san6['payment']['method'] ?? null) !== null) {
            if (mb_strtolower((string) $order['paymentMethod']) !== mb_strtolower((string) $san6['payment']['method'])) {
                $conflicts[] = 'payment_method';
            }
        }

        $order['shippingDate'] = $san6['shippingDate'] ?? ($order['shippingDate'] ?? null);
        $order['deliveryDate'] = $san6['deliveryDate'] ?? ($order['deliveryDate'] ?? null);
        $order['parcels'] = is_array($san6['parcels'] ?? null) ? $san6['parcels'] : ($order['parcels'] ?? []);

        if ($conflicts !== []) {
            $order['syncBadge'] = 'conflict';
            $order['matchingConflicts'] = $conflicts;
            $order['hasConflict'] = true;
        }

        return $order;
    }
}
