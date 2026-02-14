<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Sync\San6;

class San6MatchingService
{
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
