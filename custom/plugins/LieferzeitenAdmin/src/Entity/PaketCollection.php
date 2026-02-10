<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaketEntity>
 */
class PaketCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaketEntity::class;
    }
}
