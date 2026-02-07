<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TrackingNumber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenTrackingNumberEntity>
 */
class LieferzeitenTrackingNumberCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenTrackingNumberEntity::class;
    }
}
