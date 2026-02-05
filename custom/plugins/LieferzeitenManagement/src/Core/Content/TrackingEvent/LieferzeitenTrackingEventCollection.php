<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TrackingEvent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenTrackingEventEntity>
 */
class LieferzeitenTrackingEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenTrackingEventEntity::class;
    }
}
