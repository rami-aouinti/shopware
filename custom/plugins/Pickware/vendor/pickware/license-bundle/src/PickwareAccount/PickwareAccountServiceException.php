<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\PickwareAccount;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\PickwareAccountBundle\ApiClient\PickwareAccountApiClientException;

class PickwareAccountServiceException extends Exception implements JsonApiErrorSerializable
{
    private function __construct(
        private readonly JsonApiError $jsonApiError,
    ) {
        parent::__construct($this->jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function createPickwareAccountNotConnectedError(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Pickware Account nicht verbunden',
                'en' => 'Pickware Account not connected',
            ],
            'detail' => [
                'de' => 'Die Aktion kann nicht ausgeführt werden, da der Pickware Account nicht verbunden ist. Bitte kontaktieren Sie den Pickware Support.',
                'en' => 'The action cannot be performed because the Pickware Account is not connected. Please contact Pickware support.',
            ],
        ]));
    }

    public static function createPickwareAccountApiClientException(PickwareAccountApiClientException $error): self
    {
        return new self($error->serializeToJsonApiError());
    }
}
