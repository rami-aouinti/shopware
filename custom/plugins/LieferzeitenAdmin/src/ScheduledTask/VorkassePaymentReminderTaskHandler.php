<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use LieferzeitenAdmin\Service\Notification\VorkassePaymentReminderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class VorkassePaymentReminderTaskHandler extends ScheduledTaskHandler
{
    public function __construct(private readonly VorkassePaymentReminderService $reminderService)
    {
        parent::__construct();
    }

    public static function getHandledMessages(): iterable
    {
        return [VorkassePaymentReminderTask::class];
    }

    public function run(): void
    {
        $this->reminderService->run(Context::createDefaultContext());
    }
}
