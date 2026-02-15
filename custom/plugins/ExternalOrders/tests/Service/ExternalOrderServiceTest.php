<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use Doctrine\DBAL\Connection;
use ExternalOrders\Service\ExternalOrderService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ExternalOrderServiceTest extends TestCase
{
    public function testFetchOrdersUsesOrderIdAsPrimaryIdentifierAndExternalIdAsBusinessReference(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrderEntity('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'SW-10001', 25.5, [
            'external_order_id' => 'EXT-10001',
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, new OrderCollection([$order]), null, new Criteria(), $context)
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $service = new ExternalOrderService($repository, $connection);

        $result = $service->fetchOrders($context);

        static::assertSame(1, $result['total']);
        static::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $result['orders'][0]['id']);
        static::assertSame('EXT-10001', $result['orders'][0]['externalId']);
        static::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $result['orders'][0]['orderId']);
    }

    public function testFetchOrderDetailLoadsMetadataFromExternalOrderData(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrderEntity('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'SW-20001', 10.0, []);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, new OrderCollection([$order]), null, new Criteria([$order->getId()]), $context)
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([[ 
                'id' => 'cccccccccccccccccccccccccccccccc',
                'order_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'external_id' => 'EXT-20001',
                'channel' => 'san6',
                'raw_payload' => json_encode([
                    'detail' => [
                        'orderNumber' => 'EXT-ORDER-20001',
                        'items' => [['quantity' => 2]],
                    ],
                ], JSON_THROW_ON_ERROR),
            ]]);

        $service = new ExternalOrderService($repository, $connection);

        $detail = $service->fetchOrderDetail($context, $order->getId());

        static::assertNotNull($detail);
        static::assertSame('EXT-ORDER-20001', $detail['orderNumber']);
        static::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $detail['internalOrderId']);
        static::assertSame('EXT-20001', $detail['externalId']);
    }

    public function testMarkOrdersAsTestUsesInternalOrderIds(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrderEntity('dddddddddddddddddddddddddddddddd', 'SW-30001', 15.0, []);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, new OrderCollection([$order]), null, new Criteria(), $context)
        );
        $repository->expects($this->once())->method('upsert');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([[ 
                'id' => 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
                'order_id' => 'dddddddddddddddddddddddddddddddd',
                'external_id' => 'EXT-30001',
                'channel' => 'san6',
                'raw_payload' => json_encode(['status' => 'processing'], JSON_THROW_ON_ERROR),
            ]]);
        $connection->expects($this->once())->method('update');

        $service = new ExternalOrderService($repository, $connection);

        $result = $service->markOrdersAsTest($context, ['dddddddddddddddddddddddddddddddd']);

        static::assertSame(['updated' => 1, 'alreadyMarked' => 0, 'notFound' => 0], $result);
    }

    /**
     * @param array<string, mixed>|null $customFields
     */
    private function createOrderEntity(string $id, string $orderNumber, float $amountTotal, ?array $customFields): OrderEntity
    {
        $order = $this->createMock(OrderEntity::class);
        $order->method('getId')->willReturn($id);
        $order->method('getOrderNumber')->willReturn($orderNumber);
        $order->method('getAmountTotal')->willReturn($amountTotal);
        $order->method('getOrderDateTime')->willReturn(new \DateTimeImmutable('2024-06-18 09:14:00'));
        $order->method('getCustomFields')->willReturn($customFields);

        return $order;
    }
}
