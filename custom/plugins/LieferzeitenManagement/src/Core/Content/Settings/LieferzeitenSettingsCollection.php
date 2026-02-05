<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Settings;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LieferzeitenSettingsEntity>
 */
class LieferzeitenSettingsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LieferzeitenSettingsEntity::class;
    }
}
