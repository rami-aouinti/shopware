<?php declare(strict_types=1);

namespace ExternalOrders\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ExternalOrderDataEntity>
 */
class ExternalOrderDataCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ExternalOrderDataEntity::class;
    }
}
