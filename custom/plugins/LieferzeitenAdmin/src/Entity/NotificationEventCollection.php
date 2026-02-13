<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NotificationEventEntity>
 */
class NotificationEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEventEntity::class;
    }
}
