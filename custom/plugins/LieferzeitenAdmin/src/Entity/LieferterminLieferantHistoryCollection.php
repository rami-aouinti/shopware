<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferterminLieferantHistoryEntity>
 */
class LieferterminLieferantHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferterminLieferantHistoryEntity::class;
    }
}
