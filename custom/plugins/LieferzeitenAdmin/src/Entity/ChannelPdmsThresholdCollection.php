<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ChannelPdmsThresholdEntity>
 */
class ChannelPdmsThresholdCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ChannelPdmsThresholdEntity::class;
    }
}
