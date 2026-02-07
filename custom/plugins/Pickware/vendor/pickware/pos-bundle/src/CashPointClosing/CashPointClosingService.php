<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DalPayloadSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\CashPointClosing\CustomAggregation\CashPointClosingCustomAggregation;
use Pickware\PickwarePos\CashPointClosing\CustomAggregation\LineItemTotal;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionCollection;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionEntity;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterEntity;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class CashPointClosingService
{
    private Connection $connection;
    private EntityManager $entityManager;
    private DalPayloadSerializer $dalPayloadSerializer;

    public function __construct(
        Connection $connection,
        EntityManager $entityManager,
        DalPayloadSerializer $dalPayloadSerializer,
    ) {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->dalPayloadSerializer = $dalPayloadSerializer;
    }

    /**
     * Creates a cash point closing preview for the given cash register that uses all open transactions of this cash
     * register.
     *
     * @throws CashPointClosingException
     */
    public function createCashPointClosingPreview(
        string $cashRegisterId,
        string $userId,
        Context $context,
    ): CashPointClosing {
        /** @var CashRegisterEntity $cashRegister */
        $cashRegister = $this->entityManager->findByPrimaryKey(
            CashRegisterDefinition::class,
            $cashRegisterId,
            $context,
        );
        if (!$cashRegister) {
            throw CashPointClosingException::cashRegisterNotFound($cashRegisterId);
        }
        $fiskalyClientUuid = $cashRegister->getFiscalizationConfiguration() ? $cashRegister->getFiscalizationConfiguration()->getClientUuid() : null;

        $cashPointClosing = new CashPointClosing();
        $firstTransaction = $this->getFirstTransaction($cashRegisterId, $fiskalyClientUuid, $context);
        $lastTransaction = $this->getLastTransaction($cashRegisterId, $fiskalyClientUuid, $context);
        if (!$firstTransaction || !$lastTransaction) {
            throw CashPointClosingException::transactionsMissing($cashRegisterId);
        }
        $cashPointClosing->setFirstTransactionId($firstTransaction->getId());
        $cashPointClosing->setLastTransactionId($lastTransaction->getId());

        /** @var UserEntity $user */
        $user = $this->entityManager->findByPrimaryKey(UserDefinition::class, $userId, $context);
        if (!$user) {
            throw CashPointClosingException::userNotFound($userId);
        }
        $cashPointClosing->setUserId($userId);
        $cashPointClosing->setUserSnapShot([
            'id' => $user->getId(),
            'username' => $user->getUserName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'timeZone' => $user->getTimeZone(),
        ]);

        $cashPointClosing->setCashRegisterId($cashRegisterId);
        $cashPointClosing->setCashRegisterFiskalyClientUuid($fiskalyClientUuid);

        $transactions = $this->getCashPointClosingTransactions(
            $cashRegisterId,
            $firstTransaction->getNumber(),
            $lastTransaction->getNumber(),
            $context,
        );
        $cashPointClosing->setTransactionIds(array_values(array_map(
            fn(CashPointClosingTransactionEntity $transaction): string => $transaction->getId(),
            $transactions->getElements(),
        )));
        $this->calculateCashPointClosingCustomAggregation($cashPointClosing, $transactions);
        $cashPointClosing->setCashStatement($this->createCashStatement($transactions, $context));

        $cashPointClosing->setExportCreationDate(new DateTimeImmutable());

        return $cashPointClosing;
    }

    /**
     * Creates and saves a new cash point closing for the given cash register idempotently. A cash point closing preview
     * is created anew and compared with the given cash amount and list of transaction ids.
     *
     * @param list<string> $transactionIds
     * @throws CashPointClosingException
     */
    public function createCashPointClosing(
        string $cashPointClosingId,
        string $cashRegisterId,
        string $userId,
        float $cashAmount,
        array $transactionIds,
        int $number,
        Context $context,
    ): void {
        $cashPointClosing = $this->entityManager->findByPrimaryKey(
            CashPointClosingDefinition::class,
            $cashPointClosingId,
            $context,
        );
        if ($cashPointClosing !== null) {
            // A cash point closing with the given id already exists. We assume that it was created beforehand
            // (idempotently).
            return;
        }

        RetryableTransaction::retryable(
            $this->connection,
            function() use (
                $cashPointClosingId,
                $cashRegisterId,
                $userId,
                $cashAmount,
                $transactionIds,
                $number,
                $context
            ): void {
                $cashPointClosing = $this->createCashPointClosingPreview($cashRegisterId, $userId, $context);
                if (
                    !$this->areArraysSame($cashPointClosing->getTransactionIds(), $transactionIds)
                    || (abs($cashPointClosing->getCashStatement()->getPayment()->getCashAmount() - $cashAmount) > PHP_FLOAT_EPSILON)
                ) {
                    throw CashPointClosingException::cashPointClosingChanged();
                }

                $cashPointClosingPayload = $this->dalPayloadSerializer->getDalPayload($cashPointClosing);
                $cashPointClosingPayload['id'] = $cashPointClosingId;
                $cashPointClosingPayload['number'] = $number;

                $this->entityManager->create(
                    CashPointClosingDefinition::class,
                    [$cashPointClosingPayload],
                    $context,
                );

                $this->assignCashPointClosingToTransactions($cashPointClosing, $cashPointClosingId, $context);
            },
        );
    }

    private function getFirstTransaction(
        string $cashRegisterId,
        ?string $fiskalyClientUuid,
        Context $context,
    ): ?CashPointClosingTransactionEntity {
        $firstTransactionCriteria = new Criteria();
        $firstTransactionCriteria
            ->addFilter(new EqualsFilter('cashRegisterId', $cashRegisterId))
            ->addFilter(new EqualsFilter('cashPointClosingId', null))
            ->addSorting(new FieldSorting('number', FieldSorting::ASCENDING))
            ->setLimit(1);

        if ($fiskalyClientUuid) {
            $firstTransactionCriteria->addFilter(
                new ContainsFilter('fiscalizationContext.fiskalyDe.clientUuid', $fiskalyClientUuid),
            );
        }

        return $this->entityManager->findOneBy(
            CashPointClosingTransactionDefinition::class,
            $firstTransactionCriteria,
            $context,
        );
    }

    private function getLastTransaction(
        string $cashRegisterId,
        ?string $fiskalyClientUuid,
        Context $context,
    ): ?CashPointClosingTransactionEntity {
        $lastTransactionCriteria = new Criteria();
        $lastTransactionCriteria
            ->addFilter(new EqualsFilter('cashRegisterId', $cashRegisterId))
            ->addFilter(new EqualsFilter('cashPointClosingId', null))
            ->addSorting(new FieldSorting('number', FieldSorting::DESCENDING))
            ->setLimit(1);

        if ($fiskalyClientUuid) {
            $lastTransactionCriteria->addFilter(
                new ContainsFilter('fiscalizationContext.fiskalyDe.clientUuid', $fiskalyClientUuid),
            );
        }

        return $this->entityManager->findOneBy(
            CashPointClosingTransactionDefinition::class,
            $lastTransactionCriteria,
            $context,
        );
    }

    private function createCashStatement(
        CashPointClosingTransactionCollection $transactions,
        Context $context,
    ): CashStatement {
        // Determine the (one and only) currency of this cash point closing. Even if there are no transactions of type
        // "Beleg" the cash statement with values 0 must be formatted with a currency.
        // Also: There can only be one currency for each cash point closing. I.e. no transactions with different
        // currencies may exist in the same cash point closing.
        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $transactions->first()->getCurrencyId(),
            $context,
        );
        foreach ($transactions as $transaction) {
            if ($currency->getId() !== $transaction->getCurrencyId()) {
                throw CashPointClosingException::multipleCurrenciesFound([
                    $currency->getId(),
                    $transaction->getCurrencyId(),
                ]);
            }
        }

        // The cash statement is calculated by transactions of type "Beleg" (but all transactions stay part of the cash
        // point closing).
        $transactionsOfTypeBeleg = $transactions->filter(
            fn(CashPointClosingTransactionEntity $transaction) => $transaction->getType() === CashPointClosingTransactionDefinition::TYPE_BELEG,
        );
        $cashStatement = new CashStatement();
        $cashStatement->setBusinessCases($this->getBusinessCases($transactionsOfTypeBeleg, $currency));
        $cashStatement->setPayment($this->getCashStatementPayment($transactionsOfTypeBeleg, $currency));

        return $cashStatement;
    }

    /**
     * @return CashStatementBusinessCase[]
     */
    private function getBusinessCases(
        CashPointClosingTransactionCollection $transactions,
        CurrencyEntity $currency,
    ): array {
        // Sum all line item amounts by type and tax rate. The result is a list of business cases.
        $businessCaseAmountsPerTypeAndTaxRate = [];
        foreach ($transactions as $transaction) {
            foreach ($transaction->getCashPointClosingTransactionLineItems() as $lineItem) {
                foreach ($lineItem->getVatTable() as $vatTableEntry) {
                    $taxRate = $vatTableEntry['taxRate'];

                    $typeAndTaxRateKey = sprintf('%s%s', $lineItem->getType(), $taxRate);
                    if (!array_key_exists($typeAndTaxRateKey, $businessCaseAmountsPerTypeAndTaxRate)) {
                        $businessCaseAmountsPerTypeAndTaxRate[$typeAndTaxRateKey] = [
                            'type' => $lineItem->getType(),
                            'taxRate' => $taxRate,
                            'inclVat' => 0,
                            'exclVat' => 0,
                            'vat' => 0,
                        ];
                    }
                    $businessCaseAmountsPerTypeAndTaxRate[$typeAndTaxRateKey]['inclVat'] += $vatTableEntry['price'];
                    $businessCaseAmountsPerTypeAndTaxRate[$typeAndTaxRateKey]['exclVat'] += $vatTableEntry['price'] - $vatTableEntry['tax'];
                    $businessCaseAmountsPerTypeAndTaxRate[$typeAndTaxRateKey]['vat'] += $vatTableEntry['tax'];
                }
            }
        }

        $businessCasesByType = [];
        foreach ($businessCaseAmountsPerTypeAndTaxRate as $businessCaseAmountByTypeAndTaxRate) {
            // To group business cases by 'type', we temporarily use an associative array with key 'type' even though
            // these keys will be removed afterwards.
            $businessCaseType = $businessCaseAmountByTypeAndTaxRate['type'];
            if (!array_key_exists($businessCaseType, $businessCasesByType)) {
                $businessCase = new CashStatementBusinessCase();
                $businessCase->setType($businessCaseType);
                $businessCase->setAmountsPerTaxRate([]);
                $businessCasesByType[$businessCaseType] = $businessCase;
            }

            $amountPerTaxRate = new CashStatementBusinessCaseAmount();
            $amountPerTaxRate->setTaxRate($businessCaseAmountByTypeAndTaxRate['taxRate']);
            $amountPerTaxRate->setInclVat($this->roundTotalAmountByCurrency(
                $businessCaseAmountByTypeAndTaxRate['inclVat'],
                $currency,
            ));
            $amountPerTaxRate->setExclVat($this->roundTotalAmountByCurrency(
                $businessCaseAmountByTypeAndTaxRate['exclVat'],
                $currency,
            ));
            $amountPerTaxRate->setVat($this->roundTotalAmountByCurrency(
                $businessCaseAmountByTypeAndTaxRate['vat'],
                $currency,
            ));
            $businessCasesByType[$businessCaseType]->addAmountPerTaxRate($amountPerTaxRate);
        }

        return array_values($businessCasesByType);
    }

    private function getCashStatementPayment(
        CashPointClosingTransactionCollection $transactions,
        CurrencyEntity $currency,
    ): CashStatementPayment {
        $cashStatementPayment = new CashStatementPayment();
        /** @var CashStatementPaymentType[] $paymentTypesByType */
        $paymentTypesByType = [];
        $fullAmount = 0;
        $cashAmount = 0;
        foreach ($transactions as $transaction) {
            $paymentType = $transaction->getPayment()['type'];
            $paymentAmount = (float) $transaction->getPayment()['amount'];
            $fullAmount += $paymentAmount;
            if ($paymentType === CashPointClosingTransactionDefinition::PAYMENT_TYPE_BAR) {
                $cashAmount += $paymentAmount;
            }

            // Since we only allow a single currency per cash register we can group by payment type (not payment type
            // _and_ currency). To group payments by 'type', we temporarily use an associative array with key 'type'
            // even though these keys will be removed afterwards.
            if (!array_key_exists($paymentType, $paymentTypesByType)) {
                $cashStatementPaymentType = new CashStatementPaymentType();
                $cashStatementPaymentType->setType($paymentType);
                $cashStatementPaymentType->setCurrencyCode($transaction->getPayment()['currencyCode']);
                $cashStatementPaymentType->setAmount(0);
                $paymentTypesByType[$paymentType] = $cashStatementPaymentType;
            }
            $paymentTypesByType[$paymentType]->setAmount(
                $paymentTypesByType[$paymentType]->getAmount() + $paymentAmount,
            );
        }

        $cashStatementPayment->setCashAmountsByCurrency([]);
        if ($cashAmount > 0) {
            // Again, since we only allow a single currency per cash register, the 'cashAmountsByCurrency' list will
            // always contain one element (if any).
            $currencyCashAmount = new CashStatementPaymentCashAmount();
            $currencyCashAmount->setAmount($this->roundTotalAmountByCurrency($cashAmount, $currency));
            $currencyCashAmount->setCurrencyCode($currency->getIsoCode());
            $cashStatementPayment->setCashAmountsByCurrency([$currencyCashAmount]);
        }
        $cashStatementPayment->setFullAmount($this->roundTotalAmountByCurrency($fullAmount, $currency));
        $cashStatementPayment->setCashAmount($this->roundTotalAmountByCurrency($cashAmount, $currency));

        // Round all totals after they have been summed up
        $cashStatementPayment->setPaymentTypes([]);
        foreach ($paymentTypesByType as $paymentType) {
            $paymentType->setAmount($this->roundTotalAmountByCurrency($paymentType->getAmount(), $currency));
            $cashStatementPayment->addPaymentType($paymentType);
        }

        return $cashStatementPayment;
    }

    private function assignCashPointClosingToTransactions(
        CashPointClosing $cashPointClosing,
        string $cashPointClosingId,
        Context $context,
    ): void {
        $firstTransaction = $this->getFirstTransaction($cashPointClosing->getCashRegisterId(), $cashPointClosing->getCashRegisterFiskalyClientUuid(), $context);
        $lastTransaction = $this->getLastTransaction($cashPointClosing->getCashRegisterId(), $cashPointClosing->getCashRegisterFiskalyClientUuid(), $context);
        if (!$firstTransaction || !$lastTransaction) {
            throw CashPointClosingException::transactionsMissing($cashPointClosing->getCashRegisterId());
        }

        $transactions = $this->getCashPointClosingTransactions(
            $cashPointClosing->getCashRegisterId(),
            $firstTransaction->getNumber(),
            $lastTransaction->getNumber(),
            $context,
        );

        $changeSets = [];
        foreach ($transactions as $transaction) {
            $changeSets[] = [
                'id' => $transaction->getId(),
                'cashPointClosingId' => $cashPointClosingId,
            ];
        }

        $this->entityManager->update(CashPointClosingTransactionDefinition::class, $changeSets, $context);
    }

    private function getCashPointClosingTransactions(
        string $cashRegisterId,
        int $firstTransactionNumber,
        int $lastTransactionNumber,
        Context $context,
    ): CashPointClosingTransactionCollection {
        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter(
                'number',
                [
                    RangeFilter::GTE => $firstTransactionNumber,
                    RangeFilter::LTE => $lastTransactionNumber,
                ],
            ),
            new EqualsFilter('cashRegisterId', $cashRegisterId),
        );
        $criteria->addAssociation('cashPointClosingTransactionLineItems');
        $criteria->addSorting(new FieldSorting('number', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('cashPointClosingTransactionLineItems.type', FieldSorting::ASCENDING));

        return new CashPointClosingTransactionCollection($this->entityManager->findBy(
            CashPointClosingTransactionDefinition::class,
            $criteria,
            $context,
        ));
    }

    private function roundTotalAmountByCurrency(float $amount, CurrencyEntity $currency): float
    {
        return (new CashRounding())->cashRound($amount, $currency->getTotalRounding());
    }

    private function areArraysSame(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }
        sort($array1);
        sort($array2);

        return $array1 === $array2;
    }

    private function calculateCashPointClosingCustomAggregation(
        CashPointClosing $cashPointClosing,
        CashPointClosingTransactionCollection $transactions,
    ): void {
        $belege = $transactions->filter(fn(CashPointClosingTransactionEntity $transaction) => $transaction->getType() === CashPointClosingTransactionDefinition::TYPE_BELEG);
        $customAggregation = CashPointClosingCustomAggregation::fromArray([]);
        foreach ($belege as $transaction) {
            $paymentType = $transaction->getPayment()['type'];
            foreach ($transaction->getCashPointClosingTransactionLineItems() as $lineItem) {
                $customAggregation->add(LineItemTotal::fromArray([
                    'lineItemType' => $lineItem->getType(),
                    'paymentType' => $paymentType,
                    'taxRate' => $lineItem->getVatTable()[0]['taxRate'], // Use only first taxRate
                    'total' => $lineItem->getTotal(),
                ]));
            }
        }
        $cashPointClosing->setCustomAggregation($customAggregation);
    }
}
