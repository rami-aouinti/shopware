<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\ShippingBundle\Carrier\Model\CarrierEntity;
use Throwable;

class ShipmentException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($this->jsonApiError->getDetail(), 0, $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function carrierNotActivated(CarrierEntity $carrier): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Versanddienstleister nicht aktiviert',
                'en' => 'Carrier not activated',
            ],
            'detail' => [
                'de' => sprintf('Versanddienstleister %s ist nicht aktiviert', $carrier->getTechnicalName()),
                'en' => sprintf('Carrier %s is not activated.', $carrier->getTechnicalName()),
            ],
        ]));
    }

    public static function carrierNotSelected(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Versanddienstleister nicht ausgewählt',
                'en' => 'Carrier not selected',
            ],
            'detail' => [
                'de' => 'Es wurde kein Versanddienstleister ausgewählt.',
                'en' => 'No carrier was selected.',
            ],
        ]));
    }
}
