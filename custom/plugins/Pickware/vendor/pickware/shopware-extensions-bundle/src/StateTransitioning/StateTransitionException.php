<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\StateTransitioning;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class StateTransitionException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_SHOPWARE_EXTENSION_BUNDLE__STATE_TRANSITIONING__';
    public const NO_TRANSITION_PATH_TO_DESTINATION_STATE_FOUND = self::ERROR_CODE_NAMESPACE . 'NO_TRANSITION_PATH_TO_DESTINATION_STATE_FOUND';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function noTransitionPathToDestinationStateFound(
        string $currentStateTechnicalName,
        string $destinationStateTechnicalName,
        string $classDefinitionName,
        string $entityId,
    ): self {
        return new self(new JsonApiError([
            'code' => self::NO_TRANSITION_PATH_TO_DESTINATION_STATE_FOUND,
            'title' => 'No transition(s) to destination state found',
            'detail' => sprintf(
                'The requested state transition from state "%s" to state "%s" is not possible for entity "%s" with id '
                . '"%s". No combination of transitions lead to the destination state.',
                $currentStateTechnicalName,
                $destinationStateTechnicalName,
                $classDefinitionName,
                $entityId,
            ),
            'meta' => [
                'currentStateTechnicalName' => $currentStateTechnicalName,
                'destinationStateTechnicalName' => $destinationStateTechnicalName,
                'classDefinitionName' => $classDefinitionName,
                'entityId' => $entityId,
            ],
        ]));
    }
}
