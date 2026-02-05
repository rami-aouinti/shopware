<?php declare(strict_types=1);

namespace LieferzeitenManagement\ScheduledTask;

use LieferzeitenManagement\Service\Task\OverdueShippingTaskGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class OverdueShippingScheduledTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<\Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly OverdueShippingTaskGenerator $taskGenerator
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [OverdueShippingScheduledTask::class];
    }

    public function run(): void
    {
        $this->taskGenerator->generate(Context::createDefaultContext());
    }
}
