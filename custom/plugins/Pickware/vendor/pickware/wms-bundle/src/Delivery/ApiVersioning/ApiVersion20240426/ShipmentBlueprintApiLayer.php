<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20240426;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwareWms\ApiVersion\ApiVersion20240426;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShipmentBlueprintApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240426();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            fn($jsonContent) => $this->transformShipmentBlueprint($jsonContent),
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}

    private function transformShipmentBlueprint(stdClass $jsonContent): void
    {
        $blueprint = $jsonContent->shipmentBlueprint ?? null;
        if ($blueprint === null) {
            return;
        }

        if (($blueprint->parcels[0]->customsInformation ?? null) !== null) {
            foreach ($blueprint->parcels[0]->customsInformation as $key => $value) {
                $blueprint->{$key} = $value;
            }
            $fees = [];
            foreach ($blueprint->fees ?? [] as $feeType => $fee) {
                $fees[] = [
                    'type' => $feeType,
                    'amount' => $fee,
                ];
            }
            $blueprint->fees = $fees;
            $blueprint->invoiceNumber = empty($blueprint->invoiceNumbers) ? null : $blueprint->invoiceNumbers[0];
            $blueprint->invoiceDate = empty($blueprint->invoiceDate) ? null : $blueprint->invoiceDate;
            $blueprint->comment = empty($blueprint->comment) ? null : $blueprint->comment;
        }

        foreach ($blueprint->parcels as $parcel) {
            foreach ($parcel->items as $item) {
                if (($item->customsInformation ?? null) !== null) {
                    foreach ($item->customsInformation as $key => $value) {
                        $item->{$key} = $value;
                    }
                    $item->customsDescription = $item->description ?? null;
                    $item->countryOfOrigin = ($item->countryIsoOfOrigin ?? null) !== null ? ['iso2Code' => $item->countryIsoOfOrigin] : null;
                    $item->unitPrice = $item->customsValue ?? null;
                    unset($item->customsInformation);
                }
            }

            if (($parcel->customsInformation ?? null) !== null) {
                unset($parcel->customsInformation);
            }
        }
    }
}
