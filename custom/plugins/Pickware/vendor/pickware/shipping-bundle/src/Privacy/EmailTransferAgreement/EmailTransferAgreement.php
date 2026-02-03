<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy\EmailTransferAgreement;

use JsonSerializable;
use Pickware\ShippingBundle\Privacy\ConfirmDataTransferInStorefrontSubscriber;

/**
 * This class represents the custom field attached to an order or sales channel context.
 */
class EmailTransferAgreement implements JsonSerializable
{
    public function __construct(
        public readonly bool $allowEmailTransfer,
    ) {}

    public static function fromSalesChannelContextPayload(array $payload): self
    {
        $allowEmailTransfer = $payload[ConfirmDataTransferInStorefrontSubscriber::CONTEXT_PAYLOAD_KEY];

        return new self(
            allowEmailTransfer: filter_var($allowEmailTransfer, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public static function fromCustomFieldSet(array $customFields): self
    {
        $allowEmailTransfer = $customFields[EmailTransferAgreementCustomField::TECHNICAL_NAME];

        return new self(
            allowEmailTransfer: filter_var($allowEmailTransfer, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function jsonSerialize(): bool
    {
        return $this->allowEmailTransfer;
    }
}
