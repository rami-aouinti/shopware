<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\OidcClientBundle\Provider;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OpenIdConnectDiscoveryProviderConfigurationFactoryException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(
        private readonly LocalizableJsonApiError $jsonApiError,
        ?Exception $previous = null,
    ) {
        parent::__construct(
            $this->jsonApiError->getDetail(),
            previous: $previous,
        );
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function authenticationServerNotReachable(?Exception $previous = null): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Authentication server not reachable',
                    'de' => 'Authentifizierungsserver nicht erreichbar',
                ],
                'detail' => [
                    'en' => 'The authentication server could not be reached. Please try again later or contact the Pickware support.',
                    'de' => 'Der Authentifizierungsserver konnte nicht erreicht werden. Bitte versuche es später erneut oder wende dich an Pickware-Support.',
                ],
            ]),
            $previous,
        );
    }
}
