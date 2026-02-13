<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PositionEntity>
 */
class PositionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PositionEntity::class;
    }
}
