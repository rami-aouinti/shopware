<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Task;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenTaskEntity>
 */
class LieferzeitenTaskCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenTaskEntity::class;
    }
}
