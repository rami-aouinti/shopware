<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class VorkassePaymentReminderTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'lieferzeiten_admin.vorkasse_payment_reminder_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
