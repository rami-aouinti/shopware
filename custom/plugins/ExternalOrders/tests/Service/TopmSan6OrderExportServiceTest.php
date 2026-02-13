<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use ExternalOrders\ScheduledTask\ExternalOrdersExportRetryTaskHandler;
use ExternalOrders\Service\TopmSan6Client;
use ExternalOrders\Service\TopmSan6OrderExportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderOrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TopmSan6OrderExportServiceTest extends TestCase
{
    public function testExportOrderUsesFileTransferUrlStrategyAndMarksSentFromTopmResponse(): void
    {
        $_ENV['APP_SECRET'] = 'test-secret';
        $connection = $this->createConnection();
        $orderId = Uuid::randomHex();
        $topmClient = $this->createMock(TopmSan6Client::class);

        $topmClient->expects(static::once())
            ->method('sendByFileTransferUrl')
            ->with(
                'https://topm.example/api',
                'api-token',
                static::callback(static fn (string $signedUrl): bool => str_contains($signedUrl, 'https://shop.example/api/export?token=')),
                2.5,
                TopmSan6Client::DEFAULT_WRITE_FUNCTION,
            )
            ->willReturn('<response><response_code>0</response_code><response_message>OK</response_message></response>');

        $topmClient->expects(static::never())->method('sendByPostXml');

        $service = $this->createService(
            $this->createOrderRepository($this->createOrderForXml($orderId)),
            $topmClient,
            $this->createConfig([
                'ExternalOrders.config.externalOrdersSan6SendStrategy' => 'filetransferurl',
                'ExternalOrders.config.externalOrdersSan6BaseUrl' => 'https://topm.example/api',
                'ExternalOrders.config.externalOrdersSan6Authentifizierung' => 'api-token',
                'ExternalOrders.config.externalOrdersTimeout' => 2.5,
                'core.basicInformation.shopwareUrl' => 'https://shop.example',
            ]),
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createUrlGenerator('/api/export?token=')
        );

        $result = $service->exportOrder($orderId, Context::createDefaultContext());

        static::assertSame('sent', $result['status']);
        static::assertSame(0, $result['responseCode']);
        static::assertSame('OK', $result['responseMessage']);

        $row = $connection->fetchAssociative('SELECT status, response_code, response_message FROM external_order_export WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($result['exportId']),
        ]);

        static::assertIsArray($row);
        static::assertSame('sent', $row['status']);
        static::assertSame(0, (int) $row['response_code']);
        static::assertSame('OK', $row['response_message']);
    }

    public function testExportOrderUsesPostXmlStrategyAndSchedulesRetryOnFailedResponse(): void
    {
        $connection = $this->createConnection();
        $orderId = Uuid::randomHex();
        $topmClient = $this->createMock(TopmSan6Client::class);

        $topmClient->expects(static::once())
            ->method('sendByPostXml')
            ->with(
                'https://topm.example/api',
                'api-token',
                static::callback(static fn (string $xml): bool => str_contains($xml, '<AuftragNeu2>')),
                2.5,
                TopmSan6Client::DEFAULT_WRITE_FUNCTION,
            )
            ->willReturn('<response><response_code>9</response_code><response_message>Rejected</response_message></response>');

        $topmClient->expects(static::never())->method('sendByFileTransferUrl');

        $service = $this->createService(
            $this->createOrderRepository($this->createOrderForXml($orderId)),
            $topmClient,
            $this->createConfig([
                'ExternalOrders.config.externalOrdersSan6SendStrategy' => 'post-xml',
                'ExternalOrders.config.externalOrdersSan6BaseUrl' => 'https://topm.example/api',
                'ExternalOrders.config.externalOrdersSan6Authentifizierung' => 'api-token',
                'ExternalOrders.config.externalOrdersTimeout' => 2.5,
            ]),
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createUrlGenerator('/unused')
        );

        $result = $service->exportOrder($orderId, Context::createDefaultContext());

        static::assertSame('failed', $result['status']);

        $row = $connection->fetchAssociative('SELECT status, attempts, last_error, next_retry_at FROM external_order_export WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($result['exportId']),
        ]);

        static::assertIsArray($row);
        static::assertSame('retry_scheduled', $row['status']);
        static::assertSame(1, (int) $row['attempts']);
        static::assertSame('TopM response indicates failure', $row['last_error']);
        static::assertNotNull($row['next_retry_at']);
    }

    public function testExportOrderSchedulesRetryAndRespectsRetryLimitOnException(): void
    {
        $connection = $this->createConnection();
        $orderId = Uuid::randomHex();
        $topmClient = $this->createMock(TopmSan6Client::class);
        $topmClient->method('sendByPostXml')->willThrowException(new \RuntimeException('transport down'));

        $service = $this->createService(
            $this->createOrderRepository($this->createOrderForXml($orderId)),
            $topmClient,
            $this->createConfig([
                'ExternalOrders.config.externalOrdersSan6SendStrategy' => 'post-xml',
                'ExternalOrders.config.externalOrdersSan6BaseUrl' => 'https://topm.example/api',
                'ExternalOrders.config.externalOrdersSan6Authentifizierung' => 'api-token',
            ]),
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createUrlGenerator('/unused')
        );

        try {
            $service->exportOrder($orderId, Context::createDefaultContext());
            static::fail('Expected exception not thrown.');
        } catch (\RuntimeException $exception) {
            static::assertSame('transport down', $exception->getMessage());
        }

        $scheduled = $connection->fetchAssociative('SELECT status, attempts FROM external_order_export ORDER BY created_at DESC LIMIT 1');
        static::assertIsArray($scheduled);
        static::assertSame('retry_scheduled', $scheduled['status']);
        static::assertSame(1, (int) $scheduled['attempts']);

        $exportId = Uuid::randomHex();
        $connection->insert('external_order_export', [
            'id' => Uuid::fromHexToBytes($exportId),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'status' => 'retry_scheduled',
            'strategy' => 'post-xml',
            'attempts' => 4,
            'request_xml' => '<xml/>',
            'correlation_id' => Uuid::randomHex(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $method = new \ReflectionMethod($service, 'scheduleRetry');
        $method->setAccessible(true);
        $method->invoke($service, $exportId, 'boom');

        $permanent = $connection->fetchAssociative('SELECT status, attempts, next_retry_at FROM external_order_export WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($exportId),
        ]);
        static::assertIsArray($permanent);
        static::assertSame('failed_permanent', $permanent['status']);
        static::assertSame(5, (int) $permanent['attempts']);
        static::assertNull($permanent['next_retry_at']);
    }

    public function testServeSignedExportXmlValidTokenExpiredAndInvalidSignature(): void
    {
        $_ENV['APP_SECRET'] = 'test-secret';
        $connection = $this->createConnection();
        $orderId = Uuid::randomHex();
        $exportId = Uuid::randomHex();
        $connection->insert('external_order_export', [
            'id' => Uuid::fromHexToBytes($exportId),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'status' => 'sent',
            'strategy' => 'filetransferurl',
            'attempts' => 0,
            'request_xml' => '<export>ok</export>',
            'correlation_id' => Uuid::randomHex(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $service = $this->createService(
            $this->createMock(EntityRepository::class),
            $this->createMock(TopmSan6Client::class),
            $this->createConfig([]),
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createUrlGenerator('/unused')
        );

        $validToken = $this->createSignedToken($exportId, time() + 60, 'test-secret');
        $expiredToken = $this->createSignedToken($exportId, time() - 60, 'test-secret');
        $invalidSignatureToken = $this->createSignedToken($exportId, time() + 60, 'wrong-secret');

        static::assertSame('<export>ok</export>', $service->serveSignedExportXml($validToken));
        static::assertNull($service->serveSignedExportXml($expiredToken));
        static::assertNull($service->serveSignedExportXml($invalidSignatureToken));
    }

    public function testGetLatestExportStatusReturnsLatestRecordOrNull(): void
    {
        $connection = $this->createConnection();
        $orderId = Uuid::randomHex();
        $firstExportId = Uuid::randomHex();
        $latestExportId = Uuid::randomHex();

        $connection->insert('external_order_export', [
            'id' => Uuid::fromHexToBytes($firstExportId),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'status' => 'failed',
            'strategy' => 'post-xml',
            'attempts' => 1,
            'request_xml' => '<xml/>',
            'response_code' => 9,
            'response_message' => 'first fail',
            'correlation_id' => Uuid::randomHex(),
            'created_at' => '2026-01-01 00:00:00.000',
            'updated_at' => '2026-01-01 00:00:00.000',
        ]);

        $connection->insert('external_order_export', [
            'id' => Uuid::fromHexToBytes($latestExportId),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'status' => 'sent',
            'strategy' => 'post-xml',
            'attempts' => 0,
            'request_xml' => '<xml/>',
            'response_code' => 0,
            'response_message' => 'ok',
            'correlation_id' => 'corr-2',
            'created_at' => '2026-01-02 00:00:00.000',
            'updated_at' => '2026-01-02 00:00:00.000',
        ]);

        $service = $this->createService(
            $this->createMock(EntityRepository::class),
            $this->createMock(TopmSan6Client::class),
            $this->createConfig([]),
            $connection,
            $this->createMock(LoggerInterface::class),
            $this->createUrlGenerator('/unused')
        );

        $status = $service->getLatestExportStatus($orderId);
        static::assertIsArray($status);
        static::assertSame($latestExportId, $status['exportId']);
        static::assertSame('sent', $status['status']);
        static::assertSame(0, $status['responseCode']);
        static::assertSame('ok', $status['responseMessage']);
        static::assertSame('corr-2', $status['correlationId']);

        static::assertNull($service->getLatestExportStatus(Uuid::randomHex()));
    }

    public function testRetryTaskHandlerCallsProcessRetries(): void
    {
        $service = $this->createMock(TopmSan6OrderExportService::class);
        $service->expects(static::once())->method('processRetries');

        $handler = new ExternalOrdersExportRetryTaskHandler($service);
        $handler->run();
    }

    private function createService(
        EntityRepository $orderRepository,
        TopmSan6Client $topmClient,
        SystemConfigService $systemConfigService,
        Connection $connection,
        LoggerInterface $logger,
        UrlGeneratorInterface $urlGenerator
    ): TopmSan6OrderExportService {
        return new TopmSan6OrderExportService(
            $orderRepository,
            $topmClient,
            $systemConfigService,
            $connection,
            $logger,
            $urlGenerator
        );
    }

    private function createOrderRepository(OrderEntity $order): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($order);
        $repository->method('search')->willReturn($searchResult);

        return $repository;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createConfig(array $values): SystemConfigService
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (string $key) => $values[$key] ?? null);

        return $config;
    }

    private function createUrlGenerator(string $prefix): UrlGeneratorInterface
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')
            ->willReturnCallback(static fn (string $name, array $params): string => $prefix . $params['token']);

        return $generator;
    }

    private function createConnection(): Connection
    {
        $connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $connection->executeStatement('CREATE TABLE external_order_export (
            id BLOB PRIMARY KEY,
            order_id BLOB NOT NULL,
            status TEXT NOT NULL,
            strategy TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            request_xml TEXT,
            response_code INTEGER,
            response_message TEXT,
            response_xml TEXT,
            last_error TEXT,
            next_retry_at TEXT,
            correlation_id TEXT,
            created_at TEXT,
            updated_at TEXT
        )');

        return $connection;
    }

    private function createOrderForXml(string $orderId): OrderEntity
    {
        $billing = new OrderAddressEntity();
        $billing->setCompany('ACME');
        $billing->setFirstName('Jean');
        $billing->setLastName('Dupont');
        $billing->setStreet('Main St 1');
        $billing->setZipcode('75001');
        $billing->setCity('Paris');

        $shipping = new OrderAddressEntity();
        $shipping->setFirstName('Anna');
        $shipping->setLastName('Martin');
        $shipping->setStreet('Rue 9');
        $shipping->setZipcode('1000');
        $shipping->setCity('Lyon');

        $delivery = new OrderDeliveryEntity();
        $delivery->setShippingOrderAddress($shipping);

        $price = $this->createMock(\Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice::class);
        $price->method('getUnitPrice')->willReturn(9.99);

        $lineItem = $this->createMock(OrderLineItemEntity::class);
        $lineItem->method('getType')->willReturn('product');
        $lineItem->method('getIdentifier')->willReturn('SKU-100');
        $lineItem->method('getReferencedId')->willReturn(null);
        $lineItem->method('getLabel')->willReturn('Product Label');
        $lineItem->method('getQuantity')->willReturn(2);
        $lineItem->method('getPrice')->willReturn($price);
        $lineItem->method('getPayload')->willReturn(['Gr' => '3']);

        $customer = new OrderCustomerEntity();
        $customer->setEmail('buyer@example.test');

        $order = $this->createMock(OrderEntity::class);
        $order->method('getId')->willReturn($orderId);
        $order->method('getOrderNumber')->willReturn('ORDER-123');
        $order->method('getOrderDateTime')->willReturn(new \DateTimeImmutable('2026-01-17 10:11:12'));
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getDeliveries')->willReturn(new OrderDeliveryCollection([$delivery]));
        $order->method('getLineItems')->willReturn(new OrderLineItemCollection([$lineItem]));
        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCustomFields')->willReturn([]);

        return $order;
    }

    private function createSignedToken(string $exportId, int $expiresAt, string $secret): string
    {
        $signature = hash_hmac('sha256', $exportId . '|' . $expiresAt, $secret);
        $token = base64_encode($exportId . '|' . $expiresAt . '|' . $signature);

        return rtrim(strtr($token, '+/', '-_'), '=');
    }
}
