<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class NotificationDispatchTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'lieferzeiten_admin.notification_dispatch_task';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}
