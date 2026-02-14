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

        $normalizedParcels = $this->normalizeParcels($san6, $order);


        $order['customerFirstName'] = $order['customerFirstName']
            ?? ($san6['customer']['firstName'] ?? $san6['customer']['firstname'] ?? null);
        $order['customerLastName'] = $order['customerLastName']
            ?? ($san6['customer']['lastName'] ?? $san6['customer']['lastname'] ?? null);
        $order['customerAdditionalName'] = $order['customerAdditionalName']
            ?? ($san6['customer']['additionalName'] ?? $san6['customer']['company'] ?? null);

        $order['shippingDate'] = $san6['shippingDate'] ?? ($order['shippingDate'] ?? null);
        $order['deliveryDate'] = $san6['deliveryDate'] ?? ($order['deliveryDate'] ?? null);
        $order['parcels'] = $normalizedParcels;

        if ($normalizedParcels !== []) {
            $order['parcelRows'] = $normalizedParcels;
        }

        if ($conflicts !== []) {
            $order['syncBadge'] = 'conflict';
            $order['matchingConflicts'] = $conflicts;
            $order['hasConflict'] = true;
        }

        return $order;
    }

    /**
     * @param array<string,mixed> $san6
     * @param array<string,mixed> $order
     * @return array<int,array<string,mixed>>
     */
    private function normalizeParcels(array $san6, array $order): array
    {
        $rawParcels = $san6['parcels'] ?? ($order['parcels'] ?? []);
        if (!is_array($rawParcels)) {
            return [];
        }

        $rows = [];
        foreach ($rawParcels as $index => $parcel) {
            if (!is_array($parcel)) {
                continue;
            }

            $trackingNumber = (string) ($parcel['trackingNumber'] ?? $parcel['sendenummer'] ?? '');
            $parcelNumber = (string) ($parcel['paketNumber'] ?? $parcel['packageNumber'] ?? $parcel['parcelNumber'] ?? $trackingNumber);

            $rows[] = [
                'parcelIndex' => $index,
                'paketNumber' => $parcelNumber !== '' ? $parcelNumber : null,
                'trackingNumber' => $trackingNumber !== '' ? $trackingNumber : null,
                'status' => $parcel['status'] ?? $parcel['trackingStatus'] ?? $parcel['state'] ?? null,
                'shippingDate' => $parcel['shippingDate'] ?? $parcel['shipping_date'] ?? ($san6['shippingDate'] ?? null),
                'deliveryDate' => $parcel['deliveryDate'] ?? $parcel['delivery_date'] ?? ($san6['deliveryDate'] ?? null),
                'carrier' => $parcel['carrier'] ?? null,
            ] + $parcel;
        }

        return $rows;
    }
}
