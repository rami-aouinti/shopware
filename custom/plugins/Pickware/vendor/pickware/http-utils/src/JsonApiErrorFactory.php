<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils;

use Pickware\HttpUtils\JsonApi\JsonApiError;

class JsonApiErrorFactory
{
    /**
     * @param string $entityLabel must be formatted to fit into a written sentence (e.g. 'supplier order')
     */
    public static function entityByIdNotFoundException(string $errorCode, string $entityLabel, string $id): JsonApiError
    {
        return new JsonApiError([
            'code' => $errorCode,
            'title' => sprintf('%s not found', ucfirst($entityLabel)),
            'detail' => sprintf('No %s was found with id "%s"', $entityLabel, $id),
            'meta' => [
                // Formats 'some entity label' to 'someEntityLabelId'
                lcfirst(str_replace(' ', '', ucwords($entityLabel))) . 'Id' => $id,
            ],
        ]);
    }
}
