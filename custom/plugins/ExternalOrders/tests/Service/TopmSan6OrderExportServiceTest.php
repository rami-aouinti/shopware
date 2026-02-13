<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use Doctrine\DBAL\Connection;
use ExternalOrders\Service\TopmSan6Client;
use ExternalOrders\Service\TopmSan6OrderExportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderOrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TopmSan6OrderExportServiceTest extends TestCase
{
    public function testBuildAuftragNeu2XmlUsesExpectedBusinessNodesAndFormats(): void
    {
        $service = $this->createService();
        $order = $this->createOrderForXml();

        $xmlString = $this->invokeBuildXml($service, $order);
        $xml = simplexml_load_string($xmlString);

        static::assertInstanceOf(\SimpleXMLElement::class, $xml);
        static::assertSame('ORDER-123', (string) $xml->Referenz);
        static::assertSame('2026-01-17T10:11:12', (string) $xml->Datum);
        static::assertSame('Jean', (string) $xml->Kunde->Vorname);
        static::assertSame('SKU-100', (string) $xml->Positionen->Position->Referenz);
        static::assertSame('03', (string) $xml->Positionen->Position->Gr);
        static::assertSame('2', (string) $xml->Positionen->Position->Menge);
        static::assertSame('9.99', (string) $xml->Positionen->Position->Preis);
        static::assertSame('attachment-1.txt', (string) $xml->Anlagen->Anlage->Dateiname);
        static::assertSame(base64_encode('hello world'), (string) $xml->Anlagen->Anlage->Datei);
    }

    public function testBuildAuftragNeu2XmlValidatesMandatoryNodes(): void
    {
        $service = $this->createService();
        $order = $this->createOrderForXml(orderNumber: '  ');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Generated AuftragNeu2 XML is missing mandatory node "Referenz".');

        $this->invokeBuildXml($service, $order);
    }

    private function createService(): TopmSan6OrderExportService
    {
        return new TopmSan6OrderExportService(
            $this->createMock(EntityRepository::class),
            $this->createMock(TopmSan6Client::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(Connection::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UrlGeneratorInterface::class)
        );
    }

    private function createOrderForXml(string $orderNumber = ' ORDER-123 '): OrderEntity
    {
        $billing = new OrderAddressEntity();
        $billing->setCompany(' ACME ');
        $billing->setFirstName(' Jean ');
        $billing->setLastName(' Dupont ');
        $billing->setStreet(' Main St 1 ');
        $billing->setZipcode(' 75001 ');
        $billing->setCity(' Paris ');

        $shipping = new OrderAddressEntity();
        $shipping->setFirstName(' Anna ');
        $shipping->setLastName(' Martin ');
        $shipping->setStreet(' Rue 9 ');
        $shipping->setZipcode(' 1000 ');
        $shipping->setCity(' Lyon ');

        $delivery = new OrderDeliveryEntity();
        $delivery->setShippingOrderAddress($shipping);

        $price = $this->createMock(            \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice::class
        );
        $price->method('getUnitPrice')->willReturn(9.99);

        $lineItem = $this->createMock(OrderLineItemEntity::class);
        $lineItem->method('getType')->willReturn('product');
        $lineItem->method('getIdentifier')->willReturn('SKU-100');
        $lineItem->method('getReferencedId')->willReturn(null);
        $lineItem->method('getLabel')->willReturn(' Product Label ');
        $lineItem->method('getQuantity')->willReturn(2);
        $lineItem->method('getPrice')->willReturn($price);
        $lineItem->method('getPayload')->willReturn(['Gr' => '3']);

        $customer = new OrderCustomerEntity();
        $customer->setEmail(' buyer@example.test ');

        $order = $this->createMock(OrderEntity::class);
        $order->method('getOrderNumber')->willReturn($orderNumber);
        $order->method('getOrderDateTime')->willReturn(new \DateTimeImmutable('2026-01-17 10:11:12'));
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getDeliveries')->willReturn(new OrderDeliveryCollection([$delivery]));
        $order->method('getLineItems')->willReturn(new OrderLineItemCollection([$lineItem]));
        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCustomFields')->willReturn([
            'externalOrderAttachments' => [' hello world '],
        ]);

        return $order;
    }

    private function invokeBuildXml(TopmSan6OrderExportService $service, OrderEntity $order): string
    {
        $method = new \ReflectionMethod($service, 'buildAuftragNeu2Xml');
        $method->setAccessible(true);

        /** @var string $xml */
        $xml = $method->invoke($service, $order);

        return $xml;
    }
}
