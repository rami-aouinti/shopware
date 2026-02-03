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

use JsonSerializable;

/**
 * Helper service to format JsonSerializable recursively into a (DAL payload) associative array.
 */
class DalPayloadSerializer
{
    public function __construct() {}

    public function getDalPayload(JsonSerializable $object): array
    {
        $payload = $object->jsonSerialize();

        return $this->getDalPayloadFromArray($payload);
    }

    public function getDalPayloadFromArray(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->getDalPayloadFromArray($value);

                continue;
            }
            if ($value instanceof JsonSerializable) {
                $value = $this->getDalPayload($value);
            }
            // Non-array, non-JsonSerializable properties will be returned without formatting
        }

        return $array;
    }
}
