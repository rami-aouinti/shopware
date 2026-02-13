<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<TaskAssignmentRuleEntity>
 */
class TaskAssignmentRuleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TaskAssignmentRuleEntity::class;
    }
}
