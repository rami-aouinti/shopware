<?php declare(strict_types=1);

namespace ExternalOrders\ScheduledTask;

use ExternalOrders\Service\TopmSan6OrderExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ExternalOrdersExportRetryTaskHandler extends ScheduledTaskHandler
{
    public function __construct(private readonly TopmSan6OrderExportService $exportService)
    {
        parent::__construct();
    }

    public static function getHandledMessages(): iterable
    {
        return [ExternalOrdersExportRetryTask::class];
    }

    public function run(): void
    {
        $this->exportService->processRetries(Context::createDefaultContext());
    }
}
