<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Migration;

use Doctrine\DBAL\Connection;
use ExternalOrders\Migration\Migration2025011500ExternalOrder;
use PHPUnit\Framework\TestCase;

class Migration2025011500ExternalOrderTest extends TestCase
{
    public function testUpdateIsIdempotentWhenExecutedMultipleTimes(): void
    {
        $migration = new Migration2025011500ExternalOrder();
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->with($this->stringContains('CREATE TABLE IF NOT EXISTS `external_order`'));

        $migration->update($connection);
        $migration->update($connection);
    }

    public function testUpdateDestructiveCanBeExecutedMultipleTimes(): void
    {
        $migration = new Migration2025011500ExternalOrder();
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->never())
            ->method('executeStatement');

        $migration->updateDestructive($connection);
        $migration->updateDestructive($connection);

        $this->assertTrue(true);
    }
}
