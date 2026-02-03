<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use GuzzleHttp\Utils as GuzzleUtils;
use Pickware\DsvBundle\Api\DsvShipment;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ValidateShipmentRequest extends Request
{
    public function __construct(DsvShipment $shipment)
    {
        parent::__construct(
            method: 'POST',
            uri: 'booking/v2/bookings/validate',
            body: Psr7Utils::streamFor(GuzzleUtils::jsonEncode($shipment)),
        );
    }
}
