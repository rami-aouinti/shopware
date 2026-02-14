<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\ChannelPdmsThresholdResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ChannelPdmsThresholdResolverTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);

        $this->connection->executeStatement('CREATE TABLE lieferzeiten_channel_pdms_threshold (sales_channel_id TEXT, pdms_lieferzeit TEXT, shipping_overdue_working_days INTEGER, delivery_overdue_working_days INTEGER)');
        $this->connection->executeStatement('CREATE TABLE `order` (id TEXT PRIMARY KEY, order_number TEXT, sales_channel_id TEXT, custom_fields TEXT, created_at TEXT)');
        $this->connection->executeStatement('CREATE TABLE order_line_item (id TEXT PRIMARY KEY, order_id TEXT, custom_fields TEXT, created_at TEXT)');
        $this->connection->executeStatement('CREATE TABLE sales_channel (id TEXT PRIMARY KEY, custom_fields TEXT)');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_paket (id TEXT PRIMARY KEY, paket_number TEXT, external_order_id TEXT, source_system TEXT, created_at TEXT)');
    }

    public function testResolveForOrderUsesChannelAndMappedPdmsSlotThresholds(): void
    {
        $this->connection->insert('sales_channel', [
            'id' => 'sc-1',
            'custom_fields' => json_encode(['pdms_lieferzeiten_mapping' => ['1' => 'lz-a']], JSON_THROW_ON_ERROR),
        ]);
        $this->connection->insert('order', [
            'id' => 'order-1',
            'order_number' => 'SO-1000',
            'sales_channel_id' => 'sc-1',
            'custom_fields' => '{}',
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('order_line_item', [
            'id' => 'li-1',
            'order_id' => 'order-1',
            'custom_fields' => json_encode(['pdms_slot' => 1], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:01',
        ]);
        $this->connection->insert('lieferzeiten_channel_pdms_threshold', [
            'sales_channel_id' => 'sc-1',
            'pdms_lieferzeit' => 'lz-a',
            'shipping_overdue_working_days' => 5,
            'delivery_overdue_working_days' => 7,
        ]);

        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider());
        $result = $resolver->resolveForOrder('shopware', 'SO-1000', '10');

        static::assertSame(5, $result['shipping']['workingDays']);
        static::assertSame(7, $result['delivery']['workingDays']);
        static::assertSame('14:00', $result['shipping']['cutoff']);
        static::assertSame('15:00', $result['delivery']['cutoff']);
    }

    public function testResolveForOrderUsesCustomFieldFallbackWhenOrderNumberMismatches(): void
    {
        $this->connection->insert('sales_channel', [
            'id' => 'sc-cf',
            'custom_fields' => json_encode(['pdms_lieferzeiten_mapping' => ['2' => 'lz-cf']], JSON_THROW_ON_ERROR),
        ]);
        $this->connection->insert('order', [
            'id' => 'order-cf',
            'order_number' => 'SW-2000',
            'sales_channel_id' => 'sc-cf',
            'custom_fields' => json_encode(['externalOrderId' => 'EXT-2000'], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('order_line_item', [
            'id' => 'li-cf',
            'order_id' => 'order-cf',
            'custom_fields' => json_encode(['pdms_slot' => 2], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:01',
        ]);
        $this->connection->insert('lieferzeiten_channel_pdms_threshold', [
            'sales_channel_id' => 'sc-cf',
            'pdms_lieferzeit' => 'lz-cf',
            'shipping_overdue_working_days' => 6,
            'delivery_overdue_working_days' => 8,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with(
                'PDMS threshold resolver order lookup used fallback strategy.',
                static::callback(static fn (array $context): bool => ($context['strategy'] ?? null) === 'order_custom_field_external_id')
            );

        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider(), $logger);
        $result = $resolver->resolveForOrder('shopware', 'EXT-2000', null);

        static::assertSame(6, $result['shipping']['workingDays']);
        static::assertSame(8, $result['delivery']['workingDays']);
    }

    public function testResolveForOrderUsesNestedCustomFieldFallbackWhenExternalOrderIdIsNested(): void
    {
        $this->connection->insert('sales_channel', [
            'id' => 'sc-cf-nested',
            'custom_fields' => json_encode(['pdms_lieferzeiten_mapping' => ['3' => 'lz-cf-nested']], JSON_THROW_ON_ERROR),
        ]);
        $this->connection->insert('order', [
            'id' => 'order-cf-nested',
            'order_number' => 'SW-3000',
            'sales_channel_id' => 'sc-cf-nested',
            'custom_fields' => json_encode(['integration' => ['externalOrderId' => 'EXT-3000']], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('order_line_item', [
            'id' => 'li-cf-nested',
            'order_id' => 'order-cf-nested',
            'custom_fields' => json_encode(['pdms_slot' => 3], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:01',
        ]);
        $this->connection->insert('lieferzeiten_channel_pdms_threshold', [
            'sales_channel_id' => 'sc-cf-nested',
            'pdms_lieferzeit' => 'lz-cf-nested',
            'shipping_overdue_working_days' => 4,
            'delivery_overdue_working_days' => 6,
        ]);

        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider());
        $result = $resolver->resolveForOrder('shopware', 'EXT-3000', null);

        static::assertSame(4, $result['shipping']['workingDays']);
        static::assertSame(6, $result['delivery']['workingDays']);
    }

    public function testResolveForOrderUsesPaketJoinFallbackWhenOrderNumberMismatches(): void
    {
        $this->connection->insert('sales_channel', [
            'id' => 'sc-paket',
            'custom_fields' => json_encode(['pdms_lieferzeiten_mapping' => ['4' => 'lz-paket']], JSON_THROW_ON_ERROR),
        ]);
        $this->connection->insert('order', [
            'id' => 'order-paket',
            'order_number' => 'PAK-777',
            'sales_channel_id' => 'sc-paket',
            'custom_fields' => '{}',
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('order_line_item', [
            'id' => 'li-paket',
            'order_id' => 'order-paket',
            'custom_fields' => json_encode(['pdms_slot' => 4], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:01',
        ]);
        $this->connection->insert('lieferzeiten_paket', [
            'id' => 'paket-1',
            'paket_number' => 'PAK-777',
            'external_order_id' => 'EXT-777',
            'source_system' => 'shopware',
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('lieferzeiten_channel_pdms_threshold', [
            'sales_channel_id' => 'sc-paket',
            'pdms_lieferzeit' => 'lz-paket',
            'shipping_overdue_working_days' => 2,
            'delivery_overdue_working_days' => 9,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with(
                'PDMS threshold resolver order lookup used fallback strategy.',
                static::callback(static fn (array $context): bool => ($context['strategy'] ?? null) === 'lieferzeiten_paket_external_id_join')
            );

        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider(), $logger);
        $result = $resolver->resolveForOrder('shopware', 'EXT-777', null);

        static::assertSame(2, $result['shipping']['workingDays']);
        static::assertSame(9, $result['delivery']['workingDays']);
    }

    public function testResolveForOrderUsesPaketNumberJoinFallbackWhenExternalOrderIdIsPaketNumber(): void
    {
        $this->connection->insert('sales_channel', [
            'id' => 'sc-paket-number',
            'custom_fields' => json_encode(['pdms_lieferzeiten_mapping' => ['5' => 'lz-paket-number']], JSON_THROW_ON_ERROR),
        ]);
        $this->connection->insert('order', [
            'id' => 'order-paket-number',
            'order_number' => 'PAK-888',
            'sales_channel_id' => 'sc-paket-number',
            'custom_fields' => '{}',
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('order_line_item', [
            'id' => 'li-paket-number',
            'order_id' => 'order-paket-number',
            'custom_fields' => json_encode(['pdms_slot' => 5], JSON_THROW_ON_ERROR),
            'created_at' => '2026-02-14 10:00:01',
        ]);
        $this->connection->insert('lieferzeiten_paket', [
            'id' => 'paket-2',
            'paket_number' => 'PAK-888',
            'external_order_id' => 'EXT-ALT-888',
            'source_system' => 'shopware',
            'created_at' => '2026-02-14 10:00:00',
        ]);
        $this->connection->insert('lieferzeiten_channel_pdms_threshold', [
            'sales_channel_id' => 'sc-paket-number',
            'pdms_lieferzeit' => 'lz-paket-number',
            'shipping_overdue_working_days' => 10,
            'delivery_overdue_working_days' => 11,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with(
                'PDMS threshold resolver order lookup used fallback strategy.',
                static::callback(static fn (array $context): bool => ($context['strategy'] ?? null) === 'lieferzeiten_paket_number_join')
            );

        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider(), $logger);
        $result = $resolver->resolveForOrder('shopware', 'PAK-888', null);

        static::assertSame(10, $result['shipping']['workingDays']);
        static::assertSame(11, $result['delivery']['workingDays']);
    }

    public function testResolveForOrderFallsBackToChannelDefaultsWhenThresholdEntryMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(
                'PDMS threshold resolution fell back to channel defaults.',
                static::callback(static fn (array $context): bool => ($context['reason'] ?? null) === 'sales_channel_not_resolved')
            );

        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider(), $logger);

        $result = $resolver->resolveForOrder('shopware', 'SO-404', null);

        static::assertSame(1, $result['shipping']['workingDays']);
        static::assertSame(3, $result['delivery']['workingDays']);
    }

    private function buildSettingsProvider(): ChannelDateSettingsProvider
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturn(json_encode([
            'shipping' => ['workingDays' => 1, 'cutoff' => '14:00'],
            'delivery' => ['workingDays' => 3, 'cutoff' => '15:00'],
        ], JSON_THROW_ON_ERROR));

        return new ChannelDateSettingsProvider($config, $this->connection);
    }
}
