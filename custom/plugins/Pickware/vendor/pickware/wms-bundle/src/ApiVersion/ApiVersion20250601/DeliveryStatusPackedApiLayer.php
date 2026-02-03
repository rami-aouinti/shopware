<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ApiVersion\ApiVersion20250601;

use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonApiResponseModifier;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\DalBundle\IdResolver\EntityIdResolver;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250601;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: DeliveryDefinition::ENTITY_NAME, method: 'search')]
#[EntityApiLayer(entity: PickingProcessDefinition::ENTITY_NAME, method: 'search')]
class DeliveryStatusPackedApiLayer implements ApiLayer
{
    private string $documentsCreatedStateId;
    private string $packedStateId;

    public function __construct(
        private readonly EntityIdResolver $entityIdResolver,
    ) {}

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20250601();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            fn(stdClass $jsonContent) => self::appendPackedStateToFilter(
                $jsonContent->filter ?? $jsonContent->criteria->filter ?? [],
            ),
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse) || $response->getStatusCode() !== 200) {
            return;
        }

        $this->documentsCreatedStateId = $this->entityIdResolver->resolveIdForStateMachineState(
            DeliveryStateMachine::TECHNICAL_NAME,
            DeliveryStateMachine::STATE_DOCUMENTS_CREATED,
        );
        $this->packedStateId = $this->entityIdResolver->resolveIdForStateMachineState(
            DeliveryStateMachine::TECHNICAL_NAME,
            DeliveryStateMachine::STATE_PACKED,
        );

        if ($response->headers->get('Content-Type') === 'application/vnd.api+json') {
            JsonApiResponseModifier::modifyJsonApiContentForType(
                $response,
                'pickware_wms_delivery',
                function(array &$jsonContent): void {
                    if (($jsonContent['attributes']['stateId'] ?? null) !== $this->packedStateId) {
                        return;
                    }

                    $this->setStateToDocumentsCreated($jsonContent['attributes']);
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

            $this->findAndReplaceDeliveryStatus($content);

            $response->setContent(Json::stringify($content));
        }
    }

    private static function appendPackedStateToFilter(array $criteriaProperty): void
    {
        foreach ($criteriaProperty as $criterion) {
            if (isset($criterion->queries) && is_array($criterion->queries)) {
                // Recursively process the nested queries
                self::appendPackedStateToFilter($criterion->queries);
            }

            if (
                isset($criterion->field)
                && $criterion->field === 'state.technicalName'
                && $criterion->type === 'equalsAny'
                && $criterion->value === [
                    DeliveryStateMachine::STATE_PICKED,
                    DeliveryStateMachine::STATE_DOCUMENTS_CREATED,
                ]
            ) {
                $criterion->value = [
                    DeliveryStateMachine::STATE_PICKED,
                    DeliveryStateMachine::STATE_DOCUMENTS_CREATED,
                    DeliveryStateMachine::STATE_PACKED,
                ];
            }
        }
    }

    /**
     * Recursively searches through JSON content and replaces delivery status 'packed' with 'documents_created'.
     * This method handles nested JSON e.g., associated deliveries within picking processes.
     */
    private function findAndReplaceDeliveryStatus(array &$content): void
    {
        foreach ($content as $key => &$value) {
            if (is_array($value)) {
                $this->findAndReplaceDeliveryStatus($value);
            }

            if ($key !== 'stateId' || $value !== $this->packedStateId) {
                continue;
            }

            $this->setStateToDocumentsCreated($content);
        }
    }

    private function setStateToDocumentsCreated(array &$jsonContent): void
    {
        $jsonContent['stateId'] = $this->documentsCreatedStateId;
        if (!isset($jsonContent['state'])) {
            return;
        }

        $jsonContent['state']['technicalName'] = DeliveryStateMachine::STATE_DOCUMENTS_CREATED;
        $jsonContent['state']['id'] = $this->documentsCreatedStateId;
    }
}
