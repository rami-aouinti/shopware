<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Customer;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class CustomerAlternativeEmailValidationException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP_CUSTOMER_ALTERNATIVE_EMAIL_VALIDATION__';
    public const EMAIL_IS_NOT_VALID = self::ERROR_CODE_NAMESPACE . 'EMAIL_IS_NOT_VALID';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($this->jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function alternativeEmailIsNotValid($email): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::EMAIL_IS_NOT_VALID,
                'title' => [
                    'en' => 'The alternative email for invoice sending is not valid',
                    'de' => 'Die alternative E-Mail für den Rechnungsempfang ist nicht gültig',
                ],
                'detail' => [
                    'en' => sprintf('The alternative email "%s" is not a valid email address.', $email),
                    'de' => sprintf('Die alternative E-Mail "%s" ist keine gültige E-Mail-Adresse.', $email),
                ],
                'meta' => ['email' => $email],
            ]),
        );
    }
}
