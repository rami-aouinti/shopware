<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\NotificationSettings;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenNotificationSettingsEntity>
 */
class LieferzeitenNotificationSettingsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenNotificationSettingsEntity::class;
    }
}
