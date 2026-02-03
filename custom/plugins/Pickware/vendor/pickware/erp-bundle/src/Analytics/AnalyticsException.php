<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class AnalyticsException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__ANALYTICS__';
    public const CONFIGURATION_IS_MISSING_REQUIRED_PROPERTIES = self::ERROR_CODE_NAMESPACE . 'CONFIGURATION_IS_MISSING_REQUIRED_PROPERTIES';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail() ?? 'Unknown error');
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    /**
     * Some analytics components that differ from the ones throwing this exception will need to provide the entity
     * context for this exception. Thus, analytics exceptions have the option to be enriched with a session id.
     */
    public function addAggregationSessionIdToErrorMeta(string $aggregationSessionId): self
    {
        $this->jsonApiError->setMeta(array_merge_recursive(
            $this->jsonApiError->getMeta() ?? [],
            ['aggregationSessionId' => $aggregationSessionId],
        ));

        return $this;
    }

    /**
     * @param string[] $missingPropertyNames
     */
    public static function aggregatorConfigIsMissingRequiredProperties(string $aggregationTechnicalName, array $missingPropertyNames): self
    {
        return self::configIsMissingRequiredProperties(
            'aggregator config',
            $aggregationTechnicalName,
            $missingPropertyNames,
        );
    }

    private static function configIsMissingRequiredProperties(
        string $configType,
        string $technicalName,
        array $missingPropertyNames,
    ): self {
        return new self(new JsonApiError([
            'code' => self::CONFIGURATION_IS_MISSING_REQUIRED_PROPERTIES,
            'title' => 'Analytics configuration is missing required properties.',
            'detail' => sprintf(
                'The config of type "%s" with technical name "%s" is missing the following properties: %s',
                $configType,
                $technicalName,
                implode(' ,', $missingPropertyNames),
            ),
            'meta' => [
                'configType' => $configType,
                'technicalName' => $technicalName,
                'missingPropertyNames' => array_values($missingPropertyNames),
            ],
        ]));
    }
}
