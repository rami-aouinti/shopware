<?php declare(strict_types=1);

namespace ExternalOrders\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ExternalOrdersExportRetryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'external_orders.export_retry';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}
