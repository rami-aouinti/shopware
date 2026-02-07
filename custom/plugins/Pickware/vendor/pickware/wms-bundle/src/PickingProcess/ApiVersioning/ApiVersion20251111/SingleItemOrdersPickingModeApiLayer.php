<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20251111;

use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonApiResponseProcessor;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20251111;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: PickingProcessDefinition::ENTITY_NAME, method: 'search')]
class SingleItemOrdersPickingModeApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20251111();
    }

    public function transformRequest(Request $request, Context $context): void {}

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse)) {
            return;
        }

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            if (!($response instanceof JsonApiResponse)) {
                return;
            }
            $content = JsonApiResponseProcessor::parseContent($response);
            if ($content === false) {
                return;
            }
            JsonApiResponseModifier::modifyJsonApiContentForTypes(
                $content,
                [
                    'pickware_wms_picking_process' => function(array &$jsonContent): void {
                        if (isset($jsonContent['attributes']['pickingMode']) && $jsonContent['attributes']['pickingMode'] === 'singleItemOrdersPicking') {
                            $jsonContent['attributes']['pickingMode'] = 'preCollectedBatchPicking';
                        }
                    },
                ],
            );

            $response->setContent(Json::stringify($content));
        } else {
            // If the content cannot be decoded, we want the client to receive the unmodified content as it might
            // contain an expected error. Throwing an error here would obfuscate the original content.
            try {
                $content = Json::decodeToArray($response->getContent());
            } catch (JsonException $exception) {
                return;
            }

            if (isset($content['data']['pickingMode']) && $content['data']['pickingMode'] === 'singleItemOrdersPicking') {
                $content['data']['pickingMode'] = 'preCollectedBatchPicking';
            } elseif (isset($content['data'][0])) {
                foreach ($content['data'] as &$pickingProcess) {
                    if (isset($pickingProcess['pickingMode']) && $pickingProcess['pickingMode'] === 'singleItemOrdersPicking') {
                        $pickingProcess['pickingMode'] = 'preCollectedBatchPicking';
                    }
                }
                unset($pickingProcess);
            }

            $response->setContent(Json::stringify($content));
        }
    }
}
