<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Values;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PaymentCaptureConfigValues implements JsonSerializable
{
    public function __construct(
        private readonly bool $automaticPaymentCaptureEnabled,
        private readonly array $idsOfExcludedPaymentMethods,
        private readonly array $idsOfOrderTransactionStatesForCaptureTypePayment,
        private readonly array $idsOfOrderTransactionStatesForCaptureTypeRefund,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'automaticPaymentCaptureEnabled' => $this->isAutomaticPaymentCaptureEnabled(),
            'idsOfExcludedPaymentMethods' => $this->getIdsOfExcludedPaymentMethods(),
            'idsOfOrderTransactionStatesForCaptureTypePayment' => $this->getIdsOfOrderTransactionStatesForCaptureTypePayment(),
            'idsOfOrderTransactionStatesForCaptureTypeRefund' => $this->getIdsOfOrderTransactionStatesForCaptureTypeRefund(),
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            automaticPaymentCaptureEnabled: $array['automaticPaymentCaptureEnabled'] ?? false,
            // The actual default values require database access and thus are set in an installation step
            // @see Pickware\DatevBundle\Installation\Steps\AddPaymentCaptureDefaultConfig
            idsOfExcludedPaymentMethods: $array['idsOfExcludedPaymentMethods'] ?? [],
            idsOfOrderTransactionStatesForCaptureTypePayment: $array['idsOfOrderTransactionStatesForCaptureTypePayment'] ?? [],
            idsOfOrderTransactionStatesForCaptureTypeRefund: $array['idsOfOrderTransactionStatesForCaptureTypeRefund'] ?? [],
        );
    }

    public function isAutomaticPaymentCaptureEnabled(): bool
    {
        return $this->automaticPaymentCaptureEnabled;
    }

    public function getIdsOfExcludedPaymentMethods(): array
    {
        return $this->idsOfExcludedPaymentMethods;
    }

    public function getIdsOfOrderTransactionStatesForCaptureTypePayment(): array
    {
        return $this->idsOfOrderTransactionStatesForCaptureTypePayment;
    }

    public function getIdsOfOrderTransactionStatesForCaptureTypeRefund(): array
    {
        return $this->idsOfOrderTransactionStatesForCaptureTypeRefund;
    }
}
