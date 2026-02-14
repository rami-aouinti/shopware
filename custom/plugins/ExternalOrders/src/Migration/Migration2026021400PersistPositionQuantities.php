<?php declare(strict_types=1);

namespace ExternalOrders\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021400PersistPositionQuantities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021400;
    }

    public function update(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative('SELECT id, payload FROM external_order');

        foreach ($rows as $row) {
            $payloadRaw = $row['payload'] ?? null;
            if (!is_string($payloadRaw)) {
                continue;
            }

            $payload = json_decode($payloadRaw, true);
            if (!is_array($payload)) {
                continue;
            }

            $items = $payload['detail']['items'] ?? null;
            if (!is_array($items)) {
                continue;
            }

            $changed = false;

            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $orderedQuantity = (int) ($item['orderedQuantity'] ?? $item['quantity'] ?? 0);
                $shippedQuantity = (int) ($item['shippedQuantity'] ?? $orderedQuantity);

                if (($item['orderedQuantity'] ?? null) !== $orderedQuantity) {
                    $item['orderedQuantity'] = $orderedQuantity;
                    $changed = true;
                }

                if (($item['shippedQuantity'] ?? null) !== $shippedQuantity) {
                    $item['shippedQuantity'] = $shippedQuantity;
                    $changed = true;
                }

                if (($item['quantity'] ?? null) !== $orderedQuantity) {
                    $item['quantity'] = $orderedQuantity;
                    $changed = true;
                }

                $items[$index] = $item;
            }

            if (!$changed) {
                continue;
            }

            $payload['detail']['items'] = $items;

            $connection->executeStatement(
                'UPDATE external_order SET payload = :payload, updated_at = NOW(3) WHERE id = :id',
                [
                    'id' => $row['id'],
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
