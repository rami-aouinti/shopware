<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;

class CancelShipmentRequest extends Request
{
    public function __construct(string $shipmentIdentificationNumber)
    {
        parent::__construct(
            'DELETE',
            sprintf('void/cancel/%s', $shipmentIdentificationNumber),
            [
                'Content-Type' => 'application/json',
            ],
        );
    }
}
