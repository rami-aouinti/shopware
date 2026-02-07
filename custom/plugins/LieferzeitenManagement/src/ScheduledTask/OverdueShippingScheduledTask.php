<?php declare(strict_types=1);

namespace LieferzeitenManagement\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class OverdueShippingScheduledTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'lieferzeiten.tasks.overdue_shipping';
    }

    public static function getDefaultInterval(): int
    {
        return 3600;
    }
}
