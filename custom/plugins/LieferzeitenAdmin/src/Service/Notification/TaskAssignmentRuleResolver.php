<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Entity\TaskAssignmentRuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class TaskAssignmentRuleResolver
{
    public function __construct(private readonly EntityRepository $taskAssignmentRuleRepository)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $trigger, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('triggerKey', $trigger));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        /** @var TaskAssignmentRuleEntity|null $rule */
        $rule = $this->taskAssignmentRuleRepository->search($criteria, $context)->first();
        if ($rule === null) {
            return null;
        }

        return [
            'id' => $rule->getUniqueIdentifier(),
            'name' => $rule->getName(),
            'ruleId' => $rule->getRuleId(),
            'assigneeType' => $rule->getAssigneeType(),
            'assigneeIdentifier' => $rule->getAssigneeIdentifier(),
            'conditions' => $rule->getConditions(),
        ];
    }
}

