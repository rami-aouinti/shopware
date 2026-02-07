<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20240725;

use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20240725;
use Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20240725\TrackingCodeModifying;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: PickingProcessDefinition::ENTITY_NAME, method: 'search')]
class TrackingCodeApiLayer implements ApiLayer
{
    use TrackingCodeModifying;

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240725();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(&$jsonContent): void {
                self::replaceTrackingCodesInAssociations($jsonContent);
                self::replaceTrackingCodesInIncludes($jsonContent);
                self::replaceTrackingCodesInFilter($jsonContent);
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse)) {
            return;
        }

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            if (!($response instanceof JsonApiResponse)) {
                return;
            }
            self::transformTrackingCodesInJsonApiResponse($response);
        } else {
            // If the content cannot be decoded, we want the client to receive the unmodified content as it might
            // contain an expected error. Throwing an error here would obfuscate the original content.
            try {
                $content = Json::decodeToArray($response->getContent());
            } catch (JsonException $exception) {
                return;
            }

            if (isset($content['data']['deliveries'])) {
                self::transformParcelsInDeliveries($content['data']['deliveries']);
            } elseif (isset($content['data'][0]['deliveries'])) {
                foreach ($content['data'] as &$pickingProcess) {
                    self::transformParcelsInDeliveries($pickingProcess['deliveries']);
                }
            }

            $response->setContent(Json::stringify($content));
        }
    }
}
