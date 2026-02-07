<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Document;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\CashPointClosing\CashPointClosing;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingEntity;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionCollection;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionEntity;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class CashPointClosingDocumentContentGenerator
{
    private EntityManager $entityManager;
    private ContextFactory $contextFactory;

    public function __construct(EntityManager $entityManager, ContextFactory $contextFactory)
    {
        $this->entityManager = $entityManager;
        $this->contextFactory = $contextFactory;
    }

    public function generateFromCashPointClosingPreview(
        CashPointClosing $cashPointClosing,
        string $languageId,
        Context $context,
    ): array {
        $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $context);
        $transactionsOfTypeBeleg = $this->getTransactions(
            $cashPointClosing->getCashRegisterId(),
            $cashPointClosing->getFirstTransactionId(),
            $cashPointClosing->getLastTransactionId(),
            [new EqualsFilter('type', CashPointClosingTransactionDefinition::TYPE_BELEG)],
            $localizedContext,
        );
        $transactionsOfTypeOtherThanBeleg = $this->getTransactions(
            $cashPointClosing->getCashRegisterId(),
            $cashPointClosing->getFirstTransactionId(),
            $cashPointClosing->getLastTransactionId(),
            [new NotFilter('AND', [new EqualsFilter('type', CashPointClosingTransactionDefinition::TYPE_BELEG)])],
            $localizedContext,
        );

        // Determine first/last transaction by comparing both transaction collections that are already sorted by their
        // (int) number
        $firstTransaction = $transactionsOfTypeBeleg->first();
        if (
            $transactionsOfTypeOtherThanBeleg->first()
            && (!$firstTransaction || $transactionsOfTypeOtherThanBeleg->first()->getNumber() < $firstTransaction->getNumber())
        ) {
            $firstTransaction = $transactionsOfTypeOtherThanBeleg->first();
        }
        $lastTransaction = $transactionsOfTypeBeleg->last();
        if (
            $transactionsOfTypeOtherThanBeleg->last()
            && (!$lastTransaction || $transactionsOfTypeOtherThanBeleg->last()->getNumber() > $lastTransaction->getNumber())
        ) {
            $lastTransaction = $transactionsOfTypeOtherThanBeleg->last();
        }

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $localizedContext, ['locale']);

        /** @var CashRegisterEntity $cashRegister */
        $cashRegister = $this->entityManager->getByPrimaryKey(
            CashRegisterDefinition::class,
            $cashPointClosing->getCashRegisterId(),
            $localizedContext,
            ['branchStore'],
        );

        /** @var UserEntity $user */
        $user = $this->entityManager->getByPrimaryKey(
            UserDefinition::class,
            $cashPointClosing->getUserId(),
            $localizedContext,
        );

        return [
            'isPreview' => true,
            'localeCode' => $language->getLocale()->getCode(),
            'timeZone' => $user->getTimeZone(),
            'cashPointClosing' => $cashPointClosing,
            'transactionsOfTypeBeleg' => $transactionsOfTypeBeleg,
            'transactionsOfTypeOtherThanBeleg' => $transactionsOfTypeOtherThanBeleg,
            // Use first() and last() since the collection is sorted by number
            'firstTransaction' => $firstTransaction,
            'lastTransaction' => $lastTransaction,
            'cashRegister' => $cashRegister,
            'branchStore' => $cashRegister->getBranchStore(),
        ];
    }

    public function generateFromCashPointClosing(
        string $cashPointClosingId,
        string $languageId,
        Context $context,
    ): array {
        $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $context);
        $cashPointClosing = $this->getCashPointClosingEntity($cashPointClosingId, $localizedContext);

        $transactionsOfTypeBeleg = $cashPointClosing->getCashPointClosingTransactions()->filter(
            fn(CashPointClosingTransactionEntity $transaction) => $transaction->getType() === CashPointClosingTransactionDefinition::TYPE_BELEG,
        );
        $transactionsOfTypeOtherThanBeleg = $cashPointClosing->getCashPointClosingTransactions()->filter(
            fn(CashPointClosingTransactionEntity $transaction) => $transaction->getType() !== CashPointClosingTransactionDefinition::TYPE_BELEG,
        );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $localizedContext, ['locale']);

        return [
            'isPreview' => false,
            'localeCode' => $language->getLocale()->getCode(),
            'timeZone' => $cashPointClosing->getUser()?->getTimeZone() ?? $cashPointClosing->getUserSnapshot()['timeZone'] ?? 'UTC',
            'cashPointClosing' => $cashPointClosing,
            'transactionsOfTypeBeleg' => $transactionsOfTypeBeleg,
            'transactionsOfTypeOtherThanBeleg' => $transactionsOfTypeOtherThanBeleg,
            // Use first() and last() since the collection is sorted by number
            'firstTransaction' => $cashPointClosing->getCashPointClosingTransactions()->first(),
            'lastTransaction' => $cashPointClosing->getCashPointClosingTransactions()->last(),
            'cashRegister' => $cashPointClosing->getCashRegister(),
            'branchStore' => $cashPointClosing->getCashRegister()->getBranchStore(),
        ];
    }

    private function getCashPointClosingEntity(string $cashPointClosingId, Context $context): CashPointClosingEntity
    {
        /** @var CashPointClosingEntity $cashPointClosing */
        $cashPointClosing = $this->entityManager->getByPrimaryKey(
            CashPointClosingDefinition::class,
            $cashPointClosingId,
            $context,
            [
                'cashRegister',
                'cashRegister.branchStore',
                'user',
                'cashPointClosingTransactions',
                'cashPointClosingTransactions.currency',
                'cashPointClosingTransactions.user',
                'cashPointClosingTransactions.cashPointClosingTransactionLineItems',
            ],
        );

        // Associations are listed in an EntityCollection by id. Sort transactions manually by their number.
        $cashPointClosing->getCashPointClosingTransactions()->sort(
            fn(
                CashPointClosingTransactionEntity $transaction1,
                CashPointClosingTransactionEntity $transaction2,
            ): int => $transaction1->getNumber() - $transaction2->getNumber(),
        );

        return $cashPointClosing;
    }

    /**
     * @param Filter[] $additionalFilter
     */
    private function getTransactions(
        string $cashRegisterId,
        string $firstTransactionId,
        string $lastTransactionId,
        array $additionalFilter,
        Context $context,
    ): CashPointClosingTransactionCollection {
        /** @var CashPointClosingTransactionEntity $firstTransaction */
        $firstTransaction = $this->entityManager->getByPrimaryKey(
            CashPointClosingTransactionDefinition::class,
            $firstTransactionId,
            $context,
        );
        /** @var CashPointClosingTransactionEntity $lastTransaction */
        $lastTransaction = $this->entityManager->getByPrimaryKey(
            CashPointClosingTransactionDefinition::class,
            $lastTransactionId,
            $context,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter(
            'number',
            [
                RangeFilter::GTE => $firstTransaction->getNumber(),
                RangeFilter::LTE => $lastTransaction->getNumber(),
            ],
        ));
        $criteria->addFilter(new EqualsFilter('cashRegisterId', $cashRegisterId));
        $criteria->addFilter(...$additionalFilter);
        $criteria->addSorting(new FieldSorting('number', FieldSorting::ASCENDING));

        return new CashPointClosingTransactionCollection($this->entityManager->findBy(
            CashPointClosingTransactionDefinition::class,
            $criteria,
            $context,
            [
                'currency',
                'user',
                'cashPointClosingTransactionLineItems',
            ],
        ));
    }
}
