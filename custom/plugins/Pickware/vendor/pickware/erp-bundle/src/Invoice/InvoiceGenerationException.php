<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Invoice;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;

class InvoiceGenerationException extends Exception implements JsonApiErrorsSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__INVOICE__';
    public const ERROR_CODE_NON_CANCELLED_INVOICE_EXISTS = self::ERROR_CODE_NAMESPACE . 'NON_CANCELLED_ORDER_ALREADY_EXISTS';

    public function __construct(private readonly JsonApiErrors $jsonApiErrors)
    {
        parent::__construct($this->jsonApiErrors->getThrowableMessage());
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public static function nonCancelledInvoiceAlreadyExistsError(): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_NON_CANCELLED_INVOICE_EXISTS,
                'title' => [
                    'en' => 'There is already a non-canceled invoice for this order.',
                    'de' => 'Zu dieser Bestellung gibt es bereits eine nicht stornierte Rechnung.',
                ],
                'detail' => [
                    'en' => 'There is already a non-canceled invoice for this order.',
                    'de' => 'Zu dieser Bestellung gibt es bereits eine nicht stornierte Rechnung.',
                ],
            ]),
        ]));
    }
}
