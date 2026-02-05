<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Task;

use LieferzeitenManagement\Core\Content\TaskAssignment\LieferzeitenTaskAssignmentDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class TaskAssignmentResolver
{
    /**
     * @param EntityRepository<LieferzeitenTaskAssignmentDefinition> $taskAssignmentRepository
     */
    public function __construct(private readonly EntityRepository $taskAssignmentRepository)
    {
    }

    public function resolveAssignedUserId(?string $salesChannelId, string $taskType, Context $context, ?string $area = null): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taskType', $taskType));

        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        }

        if ($area) {
            $criteria->addFilter(new EqualsFilter('area', $area));
        }

        $criteria->setLimit(1);

        $assignment = $this->taskAssignmentRepository->search($criteria, $context)->first();

        return $assignment?->getAssignedUserId();
    }
}
