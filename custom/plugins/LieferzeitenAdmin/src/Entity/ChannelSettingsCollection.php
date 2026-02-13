<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ChannelSettingsEntity>
 */
class ChannelSettingsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ChannelSettingsEntity::class;
    }
}
