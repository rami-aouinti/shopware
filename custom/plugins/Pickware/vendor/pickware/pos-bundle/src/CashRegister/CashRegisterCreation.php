<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CashRegisterCreation
{
    private const MINIMUM_PREFIX = 100;
    private const MAXIMUM_PREFIX = 999;

    public function __construct(private readonly EntityManager $entityManager) {}

    public function createCashRegister(array $cashRegisterPayload, Context $context): void
    {
        if (isset($cashRegisterPayload['transactionNumberPrefix'])) {
            throw CashRegisterException::cashRegisterPrefixPassed();
        }

        $prefixNotNullCashRegisterCriteria = new Criteria();
        $prefixNotNullCashRegisterCriteria->addFilter(new NotFilter('OR', [
            new EqualsFilter('transactionNumberPrefix', null),
        ]));
        /** @var ?CashRegisterEntity $highestPrefixCashRegister */
        $highestPrefixCashRegister = $this->entityManager->findFirstBy(
            CashRegisterDefinition::class,
            new FieldSorting('transactionNumberPrefix', FieldSorting::DESCENDING),
            $context,
            $prefixNotNullCashRegisterCriteria,
        );

        $highestPrefix = $highestPrefixCashRegister?->getTransactionNumberPrefix();
        if (!$highestPrefix) {
            $prefix = self::MINIMUM_PREFIX;
        } else {
            $prefix = $highestPrefix + 1;
        }

        if ($prefix > self::MAXIMUM_PREFIX) {
            throw CashRegisterException::maximumCashRegisterPrefixExceeded($cashRegisterPayload['name']);
        }

        $cashRegisterPayload['transactionNumberPrefix'] = $prefix;

        $this->entityManager->createIfNotExists(CashRegisterDefinition::class, [$cashRegisterPayload], $context);
    }
}
