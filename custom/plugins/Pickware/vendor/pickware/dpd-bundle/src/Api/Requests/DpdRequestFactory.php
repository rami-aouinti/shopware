<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Requests;

use InvalidArgumentException;
use Pickware\DpdBundle\Adapter\DpdOrder;
use Pickware\DpdBundle\Config\DpdConfig;
use Pickware\ShippingBundle\Soap\SoapRequest;

class DpdRequestFactory
{
    /**
     * @param DpdOrder[] $dpdOrders
     */
    public static function makeStoreOrdersRequest(array $dpdOrders, DpdConfig $dpdConfig): SoapRequest
    {
        if (count($dpdOrders) === 0) {
            throw new InvalidArgumentException(sprintf(
                'The array passed to %s must contain at least one element.',
                __METHOD__,
            ));
        }

        return new SoapRequest(
            'storeOrders',
            [
                'order' => array_map(fn(DpdOrder $dpdOrder) => $dpdOrder->toArray(), $dpdOrders),
                'printOptions' => [
                    'printOption' => [
                        'outputFormat' => 'PDF',
                        'paperFormat' => $dpdConfig->getLabelSize()->value,
                    ],
                    'splitByParcel' => true, // splitByParcel creates one label per parcel
                ],
            ],
        );
    }
}
