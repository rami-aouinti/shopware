<?php declare(strict_types=1);

namespace LieferzeitenAdmin\ScheduledTask;

use LieferzeitenAdmin\Service\LieferzeitenImportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class LieferzeitenImportTaskHandler extends ScheduledTaskHandler
{
    public function __construct(private readonly LieferzeitenImportService $importService)
    {
        parent::__construct();
    }

    public static function getHandledMessages(): iterable
    {
        return [LieferzeitenImportTask::class];
    }

    public function run(): void
    {
        $this->importService->sync(Context::createDefaultContext(), 'scheduled');
    }
}
