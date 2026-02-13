<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SendenummerHistoryEntity>
 */
class SendenummerHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SendenummerHistoryEntity::class;
    }
}
