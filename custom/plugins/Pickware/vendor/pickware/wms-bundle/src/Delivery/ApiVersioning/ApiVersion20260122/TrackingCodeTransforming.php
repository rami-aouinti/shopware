<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20260122;

use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonApiResponseProcessor;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use stdClass;

trait TrackingCodeTransforming
{
    private static function replaceTrackingCodeFieldInFilter(stdClass &$jsonContent): void
    {
        $replaceTrackingCodeField = function(array &$filters) use (&$replaceTrackingCodeField): void {
            foreach ($filters as &$filter) {
                if (property_exists($filter, 'queries')) {
                    $replaceTrackingCodeField($filter->queries);
                } elseif (property_exists($filter, 'field')) {
                    if ($filter->field === 'parcels.trackingCodes.code') {
                        $filter->field = 'parcels.trackingCodes.trackingCode';
                    } elseif ($filter->field === 'delivery.parcels.trackingCodes.code') {
                        $filter->field = 'delivery.parcels.trackingCodes.trackingCode';
                    } elseif ($filter->field === 'deliveries.parcels.trackingCodes.code') {
                        $filter->field = 'deliveries.parcels.trackingCodes.trackingCode';
                    } elseif ($filter->field === 'pickingProcesses.deliveries.parcels.trackingCodes.code') {
                        $filter->field = 'pickingProcesses.deliveries.parcels.trackingCodes.trackingCode';
                    }
                }
            }
            unset($filter);
        };

        if (property_exists($jsonContent, 'filter')) {
            $replaceTrackingCodeField($jsonContent->filter);
        } elseif (property_exists($jsonContent, 'criteria') && property_exists($jsonContent->criteria, 'filter')) {
            $replaceTrackingCodeField($jsonContent->criteria->filter);
        }
    }

    private static function replaceTrackingCodeFieldInIncludes(stdClass &$jsonContent): void
    {
        $replaceIncludes = function(stdClass &$includes): void {
            if (isset($includes->pickware_wms_delivery_parcel_tracking_code)) {
                unset($includes->pickware_wms_delivery_parcel_tracking_code);
                $includes->pickware_shipping_tracking_code = [
                    'id',
                    'trackingCode',
                    'trackingUrl',
                ];
            }
        };

        if (property_exists($jsonContent, 'includes')) {
            $replaceIncludes($jsonContent->includes);
        } elseif (property_exists($jsonContent, 'criteria') && property_exists($jsonContent->criteria, 'includes')) {
            $replaceIncludes($jsonContent->criteria->includes);
        }
    }

    private static function transformTrackingCodesInJsonApiResponse(JsonApiResponse &$response): void
    {
        $content = JsonApiResponseProcessor::parseContent($response);
        if ($content === false) {
            return;
        }

        $trackingCodeIdToParcelId = [];
        if (isset($content['included'])) {
            foreach ($content['included'] as $element) {
                if (
                    $element['type'] === 'pickware_wms_delivery_parcel'
                    && isset($element['relationships']['trackingCodes']['data'])
                ) {
                    foreach ($element['relationships']['trackingCodes']['data'] as $tcRef) {
                        // Since a tracking code will never be related to more than one parcel, we can safely store it
                        // as a mapping from tracking code id to parcel id.
                        $trackingCodeIdToParcelId[$tcRef['id']] = $element['id'];
                    }
                }
            }
        }

        // Transform shipping tracking codes to look like old WMS delivery parcel tracking codes
        JsonApiResponseModifier::modifyJsonApiContentForTypes(
            $content,
            [
                'pickware_shipping_tracking_code' => function(array &$jsonContent) use ($trackingCodeIdToParcelId): void {
                    $jsonContent['type'] = 'pickware_wms_delivery_parcel_tracking_code';
                    $jsonContent['attributes']['code'] = $jsonContent['attributes']['trackingCode'] ?? '';
                    $jsonContent['attributes']['deliveryParcelId'] = $trackingCodeIdToParcelId[$jsonContent['id']] ?? null;

                    unset($jsonContent['attributes']['trackingCode']);
                    unset($jsonContent['attributes']['metaInformation']);
                    unset($jsonContent['attributes']['shippingDirection']);
                    unset($jsonContent['attributes']['shipmentId']);
                },
                'pickware_wms_delivery_parcel' => function(array &$jsonContent): void {
                    // Update the tracking codes relationship to point to the new type
                    if (isset($jsonContent['relationships']['trackingCodes']['data'])) {
                        foreach ($jsonContent['relationships']['trackingCodes']['data'] as &$tcRef) {
                            $tcRef['type'] = 'pickware_wms_delivery_parcel_tracking_code';
                        }
                    }
                },
            ],
        );

        $response->setContent(Json::stringify($content));
    }

    /**
     * @param array<array<string, mixed>> $deliveries
     */
    private static function transformParcelsInDeliveries(array &$deliveries): void
    {
        foreach ($deliveries as &$delivery) {
            self::transformParcelsInDelivery($delivery);
        }
        unset($delivery);
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private static function transformParcelsInDelivery(array &$delivery): void
    {
        if (!isset($delivery['parcels'])) {
            return;
        }

        foreach ($delivery['parcels'] as &$parcel) {
            if (!isset($parcel['trackingCodes'])) {
                continue;
            }
            foreach ($parcel['trackingCodes'] as &$trackingCode) {
                $trackingCode['code'] = $trackingCode['trackingCode'];
                $trackingCode['deliveryParcelId'] = $parcel['id'];
                unset($trackingCode['trackingCode']);
                unset($trackingCode['metaInformation']);
                unset($trackingCode['shippingDirection']);
                unset($trackingCode['shipmentId']);
            }
            unset($trackingCode);
        }
        unset($parcel);
    }
}
