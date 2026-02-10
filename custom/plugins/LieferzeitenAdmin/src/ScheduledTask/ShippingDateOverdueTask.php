<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ShippingDateOverdueTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'lieferzeiten_admin.shipping_date_overdue_task';
    }

    public static function getDefaultInterval(): int
    {
        return 1800;
    }
}
