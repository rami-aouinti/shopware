<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\Field;

use Pickware\DalBundle\Translation;

/**
 * Translating entities with a technical name as primary key is not possible. This field offers an
 * alternative option for translating an entity.
 */
class JsonTranslationField extends JsonSerializableObjectField
{
    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($storageName, $propertyName, Translation::class);
    }
}
