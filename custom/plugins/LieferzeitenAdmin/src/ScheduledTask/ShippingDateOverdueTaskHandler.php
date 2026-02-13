<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use LieferzeitenAdmin\Service\Notification\ShippingDateOverdueTaskService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ShippingDateOverdueTaskHandler extends ScheduledTaskHandler
{
    public function __construct(private readonly ShippingDateOverdueTaskService $service)
    {
        parent::__construct();
    }

    public static function getHandledMessages(): iterable
    {
        return [ShippingDateOverdueTask::class];
    }

    public function run(): void
    {
        $this->service->run(Context::createDefaultContext());
    }
}
