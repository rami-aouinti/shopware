<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Subscriber;

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Shopware\Core\Framework\Context;

/**
 * This service is used to update the state of goods receipt origins (supplier orders and return orders) based on the state of
 * the goods receipt line items which reference them. It should be called whenever any value which is relevant for the state
 * of the goods receipt origins changes.
 *
 * @see GoodsReceiptOriginStateUpdateSubscriber
 */
class GoodsReceiptOriginReceivedService
{
    public function __construct(private readonly EntityManager $entityManager) {}

    /**
     * @param ImmutableCollection<string> $goodsReceiptOriginLineItemIds
     */
    public function markOriginLineItemsAsFullyReceived(
        GoodsReceiptType $goodsReceiptType,
        ImmutableCollection $goodsReceiptOriginLineItemIds,
        Context $context,
    ): void {
        switch ($goodsReceiptType) {
            case GoodsReceiptType::Supplier:
                /** @var ImmutableCollection<SupplierOrderLineItemEntity> $supplierOrderLineItems */
                $supplierOrderLineItems = ImmutableCollection::create($this->entityManager->findBy(
                    SupplierOrderLineItemDefinition::class,
                    ['id' => $goodsReceiptOriginLineItemIds->asArray()],
                    $context,
                ));
                $supplierOrderLineItemUpdatePayloads = $supplierOrderLineItems
                    ->filter(fn(SupplierOrderLineItemEntity $lineItem) => $lineItem->getActualDeliveryDate() === null)
                    ->map(fn(SupplierOrderLineItemEntity $lineItem) => [
                        'id' => $lineItem->getId(),
                        'actualDeliveryDate' => new DateTime(),
                    ])->asArray();
                $this->entityManager->update(
                    SupplierOrderLineItemDefinition::class,
                    $supplierOrderLineItemUpdatePayloads,
                    $context,
                );
                break;
            case GoodsReceiptType::Customer:
            case GoodsReceiptType::Free:
                break;
        }
    }
}
