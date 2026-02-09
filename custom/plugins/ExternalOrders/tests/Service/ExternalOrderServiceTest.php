<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use ExternalOrders\Service\ExternalOrderService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ExternalOrderServiceTest extends TestCase
{
    public function testFetchOrdersReturnsDemoPayloadByDefault(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');

        $service = new ExternalOrderService($repository);

        $result = $service->fetchOrders(Context::createDefaultContext());

        $this->assertSame(5, $result['total']);
        $this->assertSame('fake-1001', $result['orders'][0]['id']);
    }

    public function testFetchOrdersUsesRepositoryWhenDemoDisabled(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrderEntity('order-1', 'ORDER-1', 2);

        $result = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $service = new ExternalOrderService($repository, false);

        $payload = $service->fetchOrders($context, null, null, 1, 50, 'orderNumber', 'ASC');

        $this->assertSame(1, $payload['total']);
        $this->assertSame(1, $payload['summary']['orderCount']);
        $this->assertSame('order-1', $payload['orders'][0]['id']);
        $this->assertSame(2, $payload['summary']['totalItems']);
    }

    public function testFetchOrderDetailReturnsDemoPayloadByDefault(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');

        $service = new ExternalOrderService($repository);

        $detail = $service->fetchOrderDetail(Context::createDefaultContext(), 'fake-1001');

        $this->assertSame('EO-1001', $detail['orderNumber']);
    }

    public function testFetchOrderDetailUsesRepositoryWhenDemoDisabled(): void
    {
        $context = Context::createDefaultContext();
        $order = $this->createOrderEntity('order-2', 'ORDER-2', 1);

        $result = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria([$order->getId()]),
            $context
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $service = new ExternalOrderService($repository, false);

        $detail = $service->fetchOrderDetail($context, $order->getId());

        $this->assertSame('ORDER-2', $detail['orderNumber']);
        $this->assertSame(1.0, $detail['totals']['sum']);
    }

    private function createOrderEntity(string $id, string $orderNumber, int $lineItemQuantity): OrderEntity
    {
        $lineItem = $this->createMock(OrderLineItemEntity::class);
        $lineItem->method('getQuantity')->willReturn($lineItemQuantity);
        $lineItem->method('getPrice')->willReturn(null);
        $lineItem->method('getLabel')->willReturn('Item');
        $lineItem->method('getId')->willReturn('line-1');

        $order = $this->createMock(OrderEntity::class);
        $order->method('getId')->willReturn($id);
        $order->method('getSalesChannelId')->willReturn('channel-1');
        $order->method('getOrderNumber')->willReturn($orderNumber);
        $order->method('getOrderCustomer')->willReturn(null);
        $order->method('getAmountTotal')->willReturn(1.0);
        $order->method('getAmountNet')->willReturn(1.0);
        $order->method('getShippingTotal')->willReturn(0.0);
        $order->method('getPositionPrice')->willReturn(1.0);
        $order->method('getLineItems')->willReturn([$lineItem]);
        $order->method('getStateMachineState')->willReturn(null);
        $order->method('getOrderDateTime')->willReturn(new \DateTimeImmutable('2024-06-18 09:14:00'));
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getDeliveries')->willReturn(null);
        $order->method('getTransactions')->willReturn(null);
        $order->method('getCustomerComment')->willReturn('');

        return $order;
    }
}
