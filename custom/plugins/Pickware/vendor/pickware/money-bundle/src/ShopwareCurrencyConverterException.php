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

class ShopwareCurrencyConverterException extends CurrencyConverterException
{
    public static function currencyNotConfigured(Currency $currency): self
    {
        return new self(sprintf(
            'Currency "%s" is not configured in your Shopware installation or its conversion factor is 0.',
            $currency->getIsoCode(),
        ));
    }
}
