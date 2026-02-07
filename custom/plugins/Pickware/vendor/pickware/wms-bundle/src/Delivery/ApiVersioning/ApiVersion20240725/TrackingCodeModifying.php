<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20240725;

use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonApiResponseProcessor;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use stdClass;

trait TrackingCodeModifying
{
    private static function replaceTrackingCodesInAssociations(stdClass &$jsonContent): void
    {
        if (property_exists($jsonContent, 'criteria')) {
            self::replaceTrackingCodesInAssociations($jsonContent->criteria);
        } elseif (!property_exists($jsonContent, 'associations')) {
            return;
        } elseif (property_exists($jsonContent->associations, 'trackingCodes')) {
            unset($jsonContent->associations->trackingCodes);
            $jsonContent->associations->parcels = new stdClass();
            $jsonContent->associations->parcels->associations = new stdClass();
            $jsonContent->associations->parcels->associations->trackingCodes = new stdClass();
        } else {
            foreach ($jsonContent->associations as $criteria) {
                if (!is_object($criteria)) {
                    continue;
                }
                self::replaceTrackingCodesInAssociations($criteria);
            }
        }
    }

    private static function replaceTrackingCodesInIncludes(stdClass &$jsonContent): void
    {
        $replaceIncludes = function(stdClass &$includes): void {
            unset($includes->pickware_wms_picking_process_tracking_code);
            $includes->pickware_wms_delivery[] = 'parcels';
            $includes->pickware_wms_delivery_parcel = [
                'id',
                'deliveryId',
                'shipped',
                'trackingCodes',
            ];
            $includes->pickware_wms_delivery_parcel_tracking_code = [
                'id',
                'deliveryParcelId',
                'code',
            ];
        };

        if (property_exists($jsonContent, 'includes')) {
            $replaceIncludes($jsonContent->includes);
        } elseif (property_exists($jsonContent, 'criteria') && property_exists($jsonContent->criteria, 'includes')) {
            $replaceIncludes($jsonContent->criteria->includes);
        }
    }

    private static function replaceTrackingCodesInFilter(stdClass &$jsonContent): void
    {
        $replaceTrackingCodesInFilter = function(array &$filters) use (&$replaceTrackingCodesInFilter): void {
            foreach ($filters as &$filter) {
                if (property_exists($filter, 'queries')) {
                    $replaceTrackingCodesInFilter($filter->queries);
                } elseif (property_exists($filter, 'field')) {
                    if ($filter->field === 'trackingCodes.code') {
                        $filter->field = 'parcels.trackingCodes.code';
                    } elseif ($filter->field === 'delivery.trackingCodes.code') {
                        $filter->field = 'delivery.parcels.trackingCodes.code';
                    }
                }
            }
            unset($filter);
        };

        if (property_exists($jsonContent, 'filter')) {
            $replaceTrackingCodesInFilter($jsonContent->filter);
        } elseif (property_exists($jsonContent, 'criteria') && property_exists($jsonContent->criteria, 'filter')) {
            $replaceTrackingCodesInFilter($jsonContent->criteria->filter);
        }
    }

    private static function transformTrackingCodesInJsonApiResponse(JsonApiResponse &$response): void
    {
        $content = JsonApiResponseProcessor::parseContent($response);
        if ($content === false) {
            return;
        }
        $trackingCodesByDeliveryParcelId = JsonApiResponseProcessor::groupValuesOfJsonApiContentForType(
            $content,
            'pickware_wms_delivery_parcel_tracking_code',
            fn(array $jsonContent): array => [
                'key' => $jsonContent['attributes']['deliveryParcelId'],
                'values' => [$jsonContent],
            ],
        );
        $trackingCodesByDeliveryId = JsonApiResponseProcessor::groupValuesOfJsonApiContentForType(
            $content,
            'pickware_wms_delivery_parcel',
            fn(array &$jsonContent): array => [
                'key' => $jsonContent['attributes']['deliveryId'],
                'values' => $trackingCodesByDeliveryParcelId[$jsonContent['id']],
            ],
        );
        JsonApiResponseModifier::modifyJsonApiContentForTypes(
            $content,
            [
                'pickware_wms_delivery_parcel_tracking_code' => function(array &$jsonContent) use ($content): void {
                    $jsonContent['attributes']['shipped'] = JsonApiResponseProcessor::getElement(
                        $content,
                        $jsonContent['attributes']['deliveryParcelId'],
                        'pickware_wms_delivery_parcel',
                    )['attributes']['shipped'];
                },
                'pickware_wms_delivery_parcel' => function(array &$jsonContent): void {
                    unset($jsonContent['attributes']['shipped']);
                },
                'pickware_wms_delivery' => function(array &$jsonContent) use ($trackingCodesByDeliveryId): void {
                    unset($jsonContent['relationships']['parcels']);
                    $jsonContent['relationships']['trackingCodes'] = new stdClass();
                    $jsonContent['relationships']['trackingCodes']->data = array_map(
                        fn($trackingCode) => [
                            'type' => 'pickware_wms_delivery_parcel_tracking_code',
                            'id' => $trackingCode['id'],
                        ],
                        $trackingCodesByDeliveryId[$jsonContent['id']] ?? [],
                    );
                },
            ],
        );

        $response->setContent(Json::stringify($content));
    }

    private static function transformParcelsInDeliveries(array &$deliveries): void
    {
        foreach ($deliveries as &$delivery) {
            self::transformParcelsInDelivery($delivery);
        }
    }

    private static function transformParcelsInDelivery(array &$delivery): void
    {
        if (!isset($delivery['parcels'])) {
            return;
        }

        $trackingCodes = [];
        foreach ($delivery['parcels'] as $parcel) {
            foreach ($parcel['trackingCodes'] as &$trackingCode) {
                $trackingCode['shipped'] = $parcel['shipped'];
            }
            unset($trackingCode);
            $trackingCodes = array_merge($trackingCodes, $parcel['trackingCodes']);
        }
        $delivery['trackingCodes'] = $trackingCodes;
        unset($delivery['parcels']);
    }
}
