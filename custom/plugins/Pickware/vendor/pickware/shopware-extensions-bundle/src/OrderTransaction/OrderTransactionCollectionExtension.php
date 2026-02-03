<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderTransaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class OrderTransactionCollectionExtension
{
    public const PRIMARY_TRANSACTION_IGNORED_STATES = [
        OrderTransactionStates::STATE_CANCELLED,
        OrderTransactionStates::STATE_FAILED,
    ];

    /**
     * Returns the order transaction that is shown by the Shopware Administration.
     *
     * This is the oldest order transaction that is not "cancelled" or "failed". If there are only "failed" or
     * "cancelled" transactions the newest order transaction is returned.
     *
     * This method basically uses the selection logic Shopware uses itself to selects the "primary order transaction".
     *
     * @see https://github.com/shopware/shopware/blob/v6.4.8.1/src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-base/index.js#L91-L98
     */
    public static function getPrimaryOrderTransaction(OrderTransactionCollection $collection): ?OrderTransactionEntity
    {
        /** @var OrderTransactionCollection $orderTransactions */
        $orderTransactions = OrderTransactionCollection::createFrom($collection);
        // Sort by createdAt ascending
        $orderTransactions->sort(
            fn(OrderTransactionEntity $a, OrderTransactionEntity $b) => $a->getCreatedAt() <=> $b->getCreatedAt(),
        );

        foreach ($orderTransactions as $orderTransaction) {
            if (
                !in_array(
                    $orderTransaction->getStateMachineState()->getTechnicalName(),
                    self::PRIMARY_TRANSACTION_IGNORED_STATES,
                    true,
                )
            ) {
                return $orderTransaction;
            }
        }

        return $orderTransactions->last();
    }
}
