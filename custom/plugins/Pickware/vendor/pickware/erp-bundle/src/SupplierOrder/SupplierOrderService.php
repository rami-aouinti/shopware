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

use DateTimeImmutable;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Shopware\Core\Framework\Context;

class SupplierOrderService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function updateExpectedDeliveryDateOfSupplierOrderAndLineItems(string $supplierOrderId, ?DateTimeImmutable $expectedDeliveryDate, Context $context): void
    {
        $this->updateDeliveryDateOfSupplierOrderAndLineItems($supplierOrderId, 'expectedDeliveryDate', $expectedDeliveryDate, $context);
    }

    public function updateActualDeliveryDateOfSupplierOrderAndLineItems(string $supplierOrderId, ?DateTimeImmutable $actualDeliveryDate, Context $context): void
    {
        $this->updateDeliveryDateOfSupplierOrderAndLineItems($supplierOrderId, 'actualDeliveryDate', $actualDeliveryDate, $context);
    }

    private function updateDeliveryDateOfSupplierOrderAndLineItems(string $supplierOrderId, string $deliveryDatePropertyName, ?DateTimeImmutable $deliveryDate, Context $context): void
    {
        $supplierOrderLineItemIds = $this->entityManager->findIdsBy(
            SupplierOrderLineItemDefinition::class,
            ['supplierOrderId' => $supplierOrderId],
            $context,
        );

        $this->entityManager->update(
            SupplierOrderDefinition::class,
            [
                [
                    'id' => $supplierOrderId,
                    $deliveryDatePropertyName => $deliveryDate,
                ],
            ],
            $context,
        );
        $this->entityManager->update(
            SupplierOrderLineItemDefinition::class,
            array_map(fn(string $supplierOrderLineItemId) => [
                'id' => $supplierOrderLineItemId,
                $deliveryDatePropertyName => $deliveryDate,
            ], $supplierOrderLineItemIds),
            $context,
        );
    }
}
