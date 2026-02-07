<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class EntityWrittenContainerEventExtension
{
    public static function hasWrittenEntities(EntityWrittenContainerEvent $entityWrittenContainerEvent): bool
    {
        return $entityWrittenContainerEvent->getEvents() && $entityWrittenContainerEvent->getEvents()->count() > 0;
    }

    public static function makeEmptyEntityWrittenContainerEvent(Context $context): EntityWrittenContainerEvent
    {
        return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
    }
}
