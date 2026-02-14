<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NeuerLieferterminPaketHistoryEntity>
 */
class NeuerLieferterminPaketHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NeuerLieferterminPaketHistoryEntity::class;
    }
}

