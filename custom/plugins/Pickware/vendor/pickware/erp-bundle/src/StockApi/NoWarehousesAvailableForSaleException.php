<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class NoWarehousesAvailableForSaleException extends Exception implements JsonApiErrorSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__NO_WAREHOUSES_AVAILABLE_FOR_SALE__';
    private const ERROR_CODE_NO_AVAILABLE_WAREHOUSES = self::ERROR_CODE_NAMESPACE . 'NO_AVAILABLE_WAREHOUSES';

    private function __construct(
        private readonly JsonApiError $jsonApiError,
    ) {
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function noAvailableWarehouses(): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_NO_AVAILABLE_WAREHOUSES,
                'title' => [
                    'en' => 'There are no warehouses defined that are available for sale',
                    'de' => 'Es sind keine Lager definiert, die für den Verkauf verfügbar sind',
                ],
                'detail' => [
                    'en' => 'There are no warehouses defined that are available for sale. ' .
                        'Please configure at least one warehouse that has stock available for sale.',
                    'de' => 'Es sind keine Lager definiert, die für den Verkauf verfügbar sind. ' .
                        'Bitte konfiguriere mindestens ein Lager, das Bestand für den Verkauf verfügbar hat.',
                ],
            ]),
        );
    }
}
