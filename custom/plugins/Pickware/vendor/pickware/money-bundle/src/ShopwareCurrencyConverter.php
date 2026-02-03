<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MoneyBundle;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * Uses the currencies configured in Shopware 6 to convert money values.
 */
class ShopwareCurrencyConverter implements CurrencyConverter
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function convertMoneyValueToCurrency(
        MoneyValue $moneyValue,
        Currency $currency,
        Context $context,
    ): MoneyValue {
        if ($moneyValue->getValue() === 0.0 || $moneyValue->getCurrency()->equals($currency)) {
            $factor = 1.0;
        } else {
            /** @var CurrencyEntity $sourceCurrency */
            $sourceCurrency = $this->entityManager->findOneBy(
                CurrencyDefinition::class,
                ['isoCode' => $moneyValue->getCurrency()->getIsoCode()],
                $context,
            );
            if (!$sourceCurrency || $sourceCurrency->getFactor() === 0.0) {
                throw ShopwareCurrencyConverterException::currencyNotConfigured($moneyValue->getCurrency());
            }
            /** @var CurrencyEntity $targetCurrency */
            $targetCurrency = $this->entityManager->findOneBy(
                CurrencyDefinition::class,
                ['isoCode' => $currency->getIsoCode()],
                $context,
            );
            if (!$targetCurrency || $targetCurrency->getFactor() === 0.0) {
                throw ShopwareCurrencyConverterException::currencyNotConfigured($moneyValue->getCurrency());
            }

            $factor = $targetCurrency->getFactor() / $sourceCurrency->getFactor();
        }

        return $moneyValue->convertTo($currency, $factor);
    }
}
