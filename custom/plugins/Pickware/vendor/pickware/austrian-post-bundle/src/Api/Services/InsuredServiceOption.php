<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api\Services;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class InsuredServiceOption extends AbstractShipmentServiceOption
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
    ) {}

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $additionalRow = [
            'Name' => 'Insured',
            'ThirdPartyID' => '011',
            'Value1' => $this->amount,
            'Value2' => $this->currency,
        ];

        $shipmentArray['FeatureList']['AdditionalInformationRow'][] = $additionalRow;
    }
}
