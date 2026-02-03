<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use GuzzleHttp\Utils as GuzzleUtils;
use Pickware\SwissPostBundle\Api\SwissPostShipment;

class CreateShipmentRequest extends Request
{
    public function __construct(SwissPostShipment $shipment)
    {
        parent::__construct(
            'POST',
            'generateAddressLabel',
            [
                'Content-Type' => 'application/json',
            ],
            Psr7Utils::streamFor(GuzzleUtils::jsonEncode($shipment)),
        );
    }
}
