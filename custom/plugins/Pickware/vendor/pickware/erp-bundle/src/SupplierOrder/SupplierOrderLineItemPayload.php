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
readonly class SupplierOrderLineItemPayload
{
    public function __construct(
        private string $supplierId,
        private array $payload,
    ) {}

    public function getSupplierId(): string
    {
        return $this->supplierId;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
