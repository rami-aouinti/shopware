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

use UnitEnum;

/**
 * Shopware's CloneTrait does not support cloning classes with enum members (because it tries to clone the enum instance).
 * This trait behaves exactly like Shopware's CloneTrait, but handles enum members correctly.
 */
trait EnumSupportingCloneTrait
{
    public function __clone()
    {
        $variables = get_object_vars($this);
        foreach ($variables as $key => $value) {
            if (is_object($value) && !($value instanceof UnitEnum)) {
                $this->$key = clone $this->$key;
            } elseif (is_array($value)) {
                $this->$key = $this->cloneArray($value);
            }
        }
    }

    private function cloneArray(array $array): array
    {
        $newValue = [];
        foreach ($array as $index => $value) {
            if (is_object($value) && !($value instanceof UnitEnum)) {
                $newValue[$index] = clone $value;
            } elseif (is_array($value)) {
                $newValue[$index] = $this->cloneArray($value);
            } else {
                $newValue[$index] = $value;
            }
        }

        return $newValue;
    }
}
