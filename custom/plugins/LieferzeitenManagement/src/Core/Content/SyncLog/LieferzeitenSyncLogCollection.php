<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\SyncLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenSyncLogEntity>
 */
class LieferzeitenSyncLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenSyncLogEntity::class;
    }
}
