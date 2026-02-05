<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TaskAssignment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenTaskAssignmentEntity>
 */
class LieferzeitenTaskAssignmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenTaskAssignmentEntity::class;
    }
}
