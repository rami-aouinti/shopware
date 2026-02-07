<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\OrderPosition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenOrderPositionEntity>
 */
class LieferzeitenOrderPositionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenOrderPositionEntity::class;
    }
}
