<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection\Events;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\Context;

/**
 * Dispatched when an invoice correction is calculated, to allow for subscribers to add custom errors,
 * preventing the invoice correction from being created.
 */
class InvoiceCorrectionValidationEvent
{
    private JsonApiErrors $jsonApiErrors;

    public function __construct(
        private readonly string $orderId,
        private readonly Context $context,
    ) {
        $this->jsonApiErrors = new JsonApiErrors();
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function addJsonApiError(JsonApiError $jsonApiError): void
    {
        $this->jsonApiErrors->addError($jsonApiError);
    }

    public function hasJsonApiErrors(): bool
    {
        return $this->jsonApiErrors->count() > 0;
    }

    public function getJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }
}
