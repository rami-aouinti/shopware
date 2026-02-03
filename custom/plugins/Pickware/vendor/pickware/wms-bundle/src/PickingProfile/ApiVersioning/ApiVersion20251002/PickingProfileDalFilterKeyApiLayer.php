<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\ApiVersioning\ApiVersion20251002;

use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20251002;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: PickingProfileDefinition::ENTITY_NAME, method: 'search')]
class PickingProfileDalFilterKeyApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20251002();
    }

    public function transformRequest(Request $request, Context $context): void {}

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse)) {
            return;
        }

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            JsonApiResponseModifier::modifyJsonApiContentForType(
                $response,
                'pickware_wms_picking_profile',
                function(array &$jsonContent): void {
                    self::addDalFilterKeyToFilterAndConvertNullValue($jsonContent['attributes']);
                },
            );
        } else {
            // If the content cannot be decoded, we want the client to receive the unmodified content as throwing an
            // error here would obfuscate the original content.
            try {
                $content = Json::decodeToArray($response->getContent());
            } catch (JsonException) {
                return;
            }

            foreach ($content['data'] as &$pickingProfile) {
                self::addDalFilterKeyToFilterAndConvertNullValue($pickingProfile);
            }
            unset($pickingProfile);

            $response->setContent(Json::stringify($content));
        }
    }

    /**
     * Move the value of the filter key to filter->_dalFilter and convert null values to an empty object.
     *
     * @param array<string, mixed> $pickingProfile
     */
    private static function addDalFilterKeyToFilterAndConvertNullValue(array &$pickingProfile): void
    {
        $pickingProfile['filter'] = [
            '_dalFilter' => $pickingProfile['filter'] ?? new stdClass(),
        ];
    }
}
