<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class LieferzeitenImportTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'lieferzeiten_admin.import_task';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}
