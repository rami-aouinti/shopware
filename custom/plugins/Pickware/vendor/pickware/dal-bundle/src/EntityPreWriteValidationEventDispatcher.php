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

/**
 * @deprecated Will be removed in 6.0.0.
 */
class EntityPreWriteValidationEventDispatcher
{
    /**
     * @deprecated Will be removed in 6.0.0. Use EntityWriteValidationEventType::getEventName() instead.
     */
    public static function getEventName(string $entityName): string
    {
        return EntityWriteValidationEventType::Pre->getEventName($entityName);
    }
}
