<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SingleItemOrder;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OpenSingleItemOrderException extends Exception implements JsonApiErrorSerializable
{
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

    public static function filteringForNonOpenSingleItemOrdersNotSupported(): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Filterung für nicht offene Einzelbestellungen wird nicht unterstützt',
                    'en' => 'Filtering for non-open single item orders is not supported',
                ],
                'detail' => [
                    'de' => 'Filterung für Bestellungen, die keine offenen Einzelbestellungen sind (z.B. `pickwareErpSingleItemOrder.isOpenSingleItemOrder = false`) wird nicht unterstützt.',
                    'en' => 'Filtering for orders that are not open single item orders (i.e. `pickwareErpSingleItemOrder.isOpenSingleItemOrder = false`) is not supported.',
                ],
            ]),
        );
    }
}
