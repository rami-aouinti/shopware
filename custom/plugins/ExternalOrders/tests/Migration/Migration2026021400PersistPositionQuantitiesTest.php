<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Migration;

use Doctrine\DBAL\Connection;
use ExternalOrders\Migration\Migration2026021400PersistPositionQuantities;
use PHPUnit\Framework\TestCase;

class Migration2026021400PersistPositionQuantitiesTest extends TestCase
{
    public function testUpdateAddsMissingOrderedAndShippedQuantityFields(): void
    {
        $migration = new Migration2026021400PersistPositionQuantities();

        $legacyPayload = [
            'detail' => [
                'items' => [
                    [
                        'name' => 'Produit A',
                        'quantity' => 3,
                        'price' => 12.5,
                    ],
                ],
            ],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with('SELECT id, payload FROM external_order')
            ->willReturn([
                [
                    'id' => random_bytes(16),
                    'payload' => json_encode($legacyPayload, JSON_THROW_ON_ERROR),
                ],
            ]);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('UPDATE external_order SET payload = :payload'),
                $this->callback(static function (array $params): bool {
                    $payload = json_decode((string) ($params['payload'] ?? ''), true);

                    return (int) ($payload['detail']['items'][0]['orderedQuantity'] ?? -1) === 3
                        && (int) ($payload['detail']['items'][0]['shippedQuantity'] ?? -1) === 3;
                }),
            );

        $migration->update($connection);
    }

    public function testUpdateDoesNothingWhenPayloadAlreadyContainsFields(): void
    {
        $migration = new Migration2026021400PersistPositionQuantities();

        $payload = [
            'detail' => [
                'items' => [
                    [
                        'quantity' => 2,
                        'orderedQuantity' => 2,
                        'shippedQuantity' => 1,
                    ],
                ],
            ],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => random_bytes(16),
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            ]);

        $connection->expects($this->never())->method('executeStatement');

        $migration->update($connection);
    }
}
