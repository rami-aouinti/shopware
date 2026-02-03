<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashMovement;

use DateTimeInterface;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemCollection;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemEntity;
use Pickware\PickwarePos\CashPointClosing\Price;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CashMovementProvider
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function countCashMovements(
        string $salesChannelId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Context $context,
    ): int {
        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('type', [
                CashPointClosingTransactionLineItemDefinition::TYPE_EINZAHLUNG,
                CashPointClosingTransactionLineItemDefinition::TYPE_AUSZAHLUNG,
            ]))
            ->addFilter(new EqualsFilter(
                'cashPointClosingTransaction.cashRegister.branchStore.salesChannelId',
                $salesChannelId,
            ))
            ->addFilter(new RangeFilter('createdAt', [
                RangeFilter::GTE => $startDate->format(DateTimeInterface::ATOM),
                RangeFilter::LTE => $endDate->format(DateTimeInterface::ATOM),
            ]))
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->setLimit(1);

        return $this->entityManager
            ->getRepository(CashPointClosingTransactionLineItemDefinition::class)
            ->searchIds($criteria, $context)
            ->getTotal();
    }

    /**
     * @return string[]
     */
    public function getCashMovementIdentifiers(
        string $salesChannelId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        int $limit,
        int $offset,
        Context $context,
    ): array {
        return $this->entityManager->findIdsBy(
            CashPointClosingTransactionLineItemDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsAnyFilter('type', [
                    CashPointClosingTransactionLineItemDefinition::TYPE_EINZAHLUNG,
                    CashPointClosingTransactionLineItemDefinition::TYPE_AUSZAHLUNG,
                ]))
                ->addFilter(new EqualsFilter(
                    'cashPointClosingTransaction.cashRegister.branchStore.salesChannelId',
                    $salesChannelId,
                ))
                ->addFilter(new RangeFilter('createdAt', [
                    RangeFilter::GTE => $startDate->format(DateTimeInterface::ATOM),
                    RangeFilter::LTE => $endDate->format(DateTimeInterface::ATOM),
                ]))
                ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
                ->addSorting(new FieldSorting('id', FieldSorting::ASCENDING))
                ->setLimit($limit)
                ->setOffset($offset),
            $context,
        );
    }

    /**
     * @param string[] $cashMovementIdentifiers
     * @return CashMovement[]
     */
    public function getCashMovements(array $cashMovementIdentifiers, Context $context): array
    {
        /** @var CashPointClosingTransactionLineItemCollection $lineItems */
        $lineItems = $this->entityManager->findBy(
            CashPointClosingTransactionLineItemDefinition::class,
            ['id' => $cashMovementIdentifiers],
            $context,
            ['cashPointClosingTransaction.cashRegister.branchStore'],
        );

        return $lineItems->map(fn(CashPointClosingTransactionLineItemEntity $lineItem) => new CashMovement(
            uniqueIdentifier: $lineItem->getId(),
            amount: Price::fromArray($lineItem->getTotal())->getInclVat(),
            currencyId: $lineItem->getCashPointClosingTransaction()->getCurrencyId(),
            type: CashMovementType::fromSerializedLineItemType($lineItem->getType()),
            salesChannelId: $lineItem->getCashPointClosingTransaction()->getCashRegister()->getBranchStore()->getSalesChannelId(),
            branchStoreName: $lineItem->getCashPointClosingTransaction()->getCashRegister()->getBranchStore()->getName(),
            cashRegisterName: $lineItem->getCashPointClosingTransaction()->getCashRegister()->getName(),
            date: $lineItem->getCreatedAt(),
            comment: $lineItem->getCashPointClosingTransaction()->getComment(),
        ));
    }
}
