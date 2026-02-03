<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\GoodsReceipt;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;

class GoodsReceiptReturnOrderReasonAssignmentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @param array<string, string> $productReturnReasons map of product IDs to return reasons
     */
    public function assignReturnReasonsForGoodsReceiptReturns(
        string $goodsReceiptId,
        array $productReturnReasons,
        Context $context,
    ): void {
        /** @var GoodsReceiptLineItemCollection $goodsReceiptLineItems */
        $goodsReceiptLineItems = $this->entityManager->findBy(
            GoodsReceiptLineItemDefinition::class,
            [
                'goodsReceiptId' => $goodsReceiptId,
                'productId' => array_keys($productReturnReasons),
            ],
            $context,
            ['returnOrder.lineItems'],
        );

        $updatePayloads = ImmutableCollection::create();
        foreach ($goodsReceiptLineItems as $goodsReceiptLineItem) {
            $updatePayloads = ImmutableCollection::create($goodsReceiptLineItem->getReturnOrder()?->getLineItems() ?? [])
                ->filter(
                    fn(ReturnOrderLineItemEntity $returnOrderLineItem) => $returnOrderLineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE
                        && $returnOrderLineItem->getProductId() === $goodsReceiptLineItem->getProductId(),
                )
                ->map(fn(ReturnOrderLineItemEntity $returnOrderLineItem) => [
                    'id' => $returnOrderLineItem->getId(),
                    'reason' => $productReturnReasons[$returnOrderLineItem->getProductId()],
                ])
                ->merge($updatePayloads);
        }

        $this->entityManager->update(
            ReturnOrderLineItemDefinition::class,
            $updatePayloads->asArray(),
            $context,
        );
    }
}
