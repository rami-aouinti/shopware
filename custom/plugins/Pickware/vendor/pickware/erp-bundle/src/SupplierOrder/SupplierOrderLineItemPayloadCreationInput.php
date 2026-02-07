<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class SupplierOrderLineItemPayloadCreationInput
{
    /**
     * @param array<string, mixed> $additionalFields
     */
    public function __construct(
        private string $productSupplierConfigurationId,
        private int $quantity,
        private ?string $supplierOrderId = null,
        private ?float $unitPrice = null,
        private array $additionalFields = [],
    ) {}

    public function getProductSupplierConfigurationId(): string
    {
        return $this->productSupplierConfigurationId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSupplierOrderId(): ?string
    {
        return $this->supplierOrderId;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalFields(): array
    {
        return $this->additionalFields;
    }
}
