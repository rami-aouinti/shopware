<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ChannelDateSettingsProviderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_channel_settings (sales_channel_id TEXT, shipping_working_days INTEGER, shipping_cutoff TEXT, delivery_working_days INTEGER, delivery_cutoff TEXT)');
    }

    public function testReturnsDefaultsWhenConfigIsMissing(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturn(null);

        $provider = new ChannelDateSettingsProvider($config, $this->connection);

        static::assertSame([
            'shipping' => ['workingDays' => 0, 'cutoff' => '12:00'],
            'delivery' => ['workingDays' => 2, 'cutoff' => '12:00'],
        ], $provider->getForChannel('shopware'));
    }

    public function testSupportsLegacyFlatConfigurationAsDeliveryFallback(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturn('{"workingDays":3,"cutoff":"15:30"}');

        $provider = new ChannelDateSettingsProvider($config, $this->connection);

        static::assertSame([
            'shipping' => ['workingDays' => 0, 'cutoff' => '15:30'],
            'delivery' => ['workingDays' => 3, 'cutoff' => '15:30'],
        ], $provider->getForChannel('shopware'));
    }

    public function testSupportsExplicitShippingAndDeliveryRules(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturn(
            '{"shipping":{"workingDays":1,"cutoff":"12:00"},"delivery":{"workingDays":4,"cutoff":"16:45"}}'
        );

        $provider = new ChannelDateSettingsProvider($config, $this->connection);

        static::assertSame([
            'shipping' => ['workingDays' => 1, 'cutoff' => '12:00'],
            'delivery' => ['workingDays' => 4, 'cutoff' => '16:45'],
        ], $provider->getForChannel('gambio'));
    }
}
