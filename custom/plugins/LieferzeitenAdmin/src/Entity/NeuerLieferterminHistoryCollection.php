<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NeuerLieferterminHistoryEntity>
 */
class NeuerLieferterminHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NeuerLieferterminHistoryEntity::class;
    }
}
