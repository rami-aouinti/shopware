<?php declare(strict_types=1);

namespace LieferzeitenManagement\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731100000AddAdditionalCustomerNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731100000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `lieferzeiten_package`
            ADD COLUMN `additional_customer_names` LONGTEXT NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
