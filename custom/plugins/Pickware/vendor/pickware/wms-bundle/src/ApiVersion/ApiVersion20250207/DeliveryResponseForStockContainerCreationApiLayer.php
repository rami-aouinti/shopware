<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ApiVersion\ApiVersion20250207;

use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonApiResponseProcessor;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250207;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20260122\TrackingCodeApiLayer as ApiVersion20260122TrackingCodeApiLayer;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryResponseForStockContainerCreationApiLayer implements ApiLayer
{
    public function __construct(
        private readonly EntityResponseService $entityResponseService,
        private readonly ApiVersion20260122TrackingCodeApiLayer $trackingCodeApiLayer,
    ) {}

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20250207();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(stdClass &$jsonContent): void {
                $jsonContent->__apiVersion20250207DeletedAssociations = $jsonContent->associations;
                $jsonContent->__apiVersion20250207OverriddenIncludes = $jsonContent->includes ?? new stdClass();
                $jsonContent->associations = new stdClass();
                $jsonContent->includes = new stdClass();
                $jsonContent->includes->pickware_wms_delivery = ['pickingProcessId'];
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse) || $response->getStatusCode() !== 200) {
            return;
        }

        // If the content cannot be decoded, we want the client to receive the unmodified content as throwing an error
        // here would obfuscate the original content.
        try {
            $responseContent = Json::decodeToArray($response->getContent());
        } catch (JsonException $exception) {
            return;
        }

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            if (!($response instanceof JsonApiResponse)) {
                return;
            }
            $delivery = JsonApiResponseProcessor::getElement(
                $responseContent,
                $request->get('deliveryId'),
                DeliveryDefinition::ENTITY_NAME,
            );
            $pickingProcessId = $delivery['attributes']['pickingProcessId'];
        } else {
            $pickingProcessId = $responseContent['data']['pickingProcessId'];
        }

        // If the content cannot be decoded, we want the client to receive the unmodified content as throwing an error
        // here would obfuscate the original content.
        try {
            $requestContent = Json::decodeToArray($request->getContent());
            $associations = $requestContent['__apiVersion20250207DeletedAssociations'];
            $includes = $requestContent['__apiVersion20250207OverriddenIncludes'];
        } catch (JsonException $exception) {
            return;
        }

        $pickingProcessResponse = $this->entityResponseService->makeEntityDetailResponse(
            entityDefinitionClass: PickingProcessDefinition::class,
            entityPrimaryKey: $pickingProcessId,
            context: $context,
            request: $request,
            associations: $associations,
            // Do not pass null because the method will otherwise use the overrides from the request
            includes: $includes ?? [],
        );

        $response->setContent($pickingProcessResponse->getContent());

        // Since this API layer "blindly" returns a picking process entity and thus disregards all api layers that were
        // previously applied to the response, we need to re-apply those here, if they are also applicable to picking
        // process responses.
        $this->trackingCodeApiLayer->transformResponse($request, $response, $context);
    }
}
