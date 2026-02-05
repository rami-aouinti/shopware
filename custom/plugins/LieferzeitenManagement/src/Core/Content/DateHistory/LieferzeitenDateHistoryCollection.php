<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\DateHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenDateHistoryEntity>
 */
class LieferzeitenDateHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenDateHistoryEntity::class;
    }
}
