<?php declare(strict_types=1);

namespace ExternalOrders\ScheduledTask;

use ExternalOrders\Service\ExternalOrderSyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ExternalOrdersSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        private readonly ExternalOrderSyncService $syncService,
    ) {
        parent::__construct();
    }

    public static function getHandledMessages(): iterable
    {
        return [ExternalOrdersSyncTask::class];
    }

    public function run(): void
    {
        $this->syncService->syncNewOrders(Context::createDefaultContext());
    }
}
