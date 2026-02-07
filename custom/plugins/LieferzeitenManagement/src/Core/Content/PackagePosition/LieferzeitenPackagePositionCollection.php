<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\PackagePosition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenPackagePositionEntity>
 */
class LieferzeitenPackagePositionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenPackagePositionEntity::class;
    }
}
