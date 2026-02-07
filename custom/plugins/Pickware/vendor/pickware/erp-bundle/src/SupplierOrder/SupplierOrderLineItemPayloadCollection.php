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

use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends Collection<SupplierOrderLineItemPayload>
 */
#[Exclude]
class SupplierOrderLineItemPayloadCollection extends Collection
{
    /**
     * @return array[]
     */
    public function getPayloadsBySupplierId(string $supplierId): array
    {
        return array_values(
            $this
                ->filter(fn(SupplierOrderLineItemPayload $payload) => $payload->getSupplierId() === $supplierId)
                ->map(fn(SupplierOrderLineItemPayload $payload) => $payload->getPayload()),
        );
    }

    /**
     * @return string[]
     */
    public function getSupplierIds(): array
    {
        return array_values(array_unique($this->map(fn(SupplierOrderLineItemPayload $payload) => $payload->getSupplierId())));
    }
}
