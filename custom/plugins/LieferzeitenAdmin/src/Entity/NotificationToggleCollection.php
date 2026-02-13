<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NotificationToggleEntity>
 */
class NotificationToggleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationToggleEntity::class;
    }
}
