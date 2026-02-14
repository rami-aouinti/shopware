<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\ChannelPdmsThresholdResolver;
use PHPUnit\Framework\TestCase;
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

    public function testResolveForOrderFallsBackToChannelDefaultsWhenThresholdEntryMissing(): void
    {
        $resolver = new ChannelPdmsThresholdResolver($this->connection, $this->buildSettingsProvider());

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
