<?php declare(strict_types=1);

namespace ExternalOrders\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ExternalOrderEntity>
 */
class ExternalOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ExternalOrderEntity::class;
    }
}
