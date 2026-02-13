<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<NotificationTemplateEntity> */
class NotificationTemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationTemplateEntity::class;
    }
}
