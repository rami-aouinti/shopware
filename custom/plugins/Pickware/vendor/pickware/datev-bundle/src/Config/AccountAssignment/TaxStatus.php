<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

enum TaxStatus
{
    case TaxFree;
    case Net;
    case Gross;

    public static function fromShopwareTaxStatus(string $shopwareTaxStatus): TaxStatus
    {
        return match ($shopwareTaxStatus) {
            CartPrice::TAX_STATE_FREE => self::TaxFree,
            CartPrice::TAX_STATE_NET => self::Net,
            CartPrice::TAX_STATE_GROSS => self::Gross,
        };
    }
}
