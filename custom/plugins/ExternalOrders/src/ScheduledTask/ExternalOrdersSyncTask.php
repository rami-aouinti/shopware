<?php declare(strict_types=1);

namespace ExternalOrders\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ExternalOrdersSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'external_orders.sync';
    }

    public static function getDefaultInterval(): int
    {
        return 7200;
    }
}
