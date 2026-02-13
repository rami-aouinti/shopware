<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use LieferzeitenAdmin\Service\Notification\QueuedNotificationEmailProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class NotificationDispatchTaskHandler extends ScheduledTaskHandler
{
    public function __construct(private readonly QueuedNotificationEmailProcessor $processor)
    {
        parent::__construct();
    }

    public static function getHandledMessages(): iterable
    {
        return [NotificationDispatchTask::class];
    }

    public function run(): void
    {
        $this->processor->run(Context::createDefaultContext());
    }
}
