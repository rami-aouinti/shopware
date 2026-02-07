<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Package;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenPackageEntity>
 */
class LieferzeitenPackageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenPackageEntity::class;
    }
}
