<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\ActivityLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenActivityLogEntity>
 */
class LieferzeitenActivityLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenActivityLogEntity::class;
    }
}
