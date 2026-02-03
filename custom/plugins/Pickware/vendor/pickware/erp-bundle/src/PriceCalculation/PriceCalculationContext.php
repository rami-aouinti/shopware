<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PriceCalculation;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PriceCalculationContext
{
    public string $taxStatus;
    public CashRoundingConfig $itemRounding;
    public CashRoundingConfig $totalRounding;

    public function __construct(
        string $taxStatus,
        CashRoundingConfig $itemRounding,
        CashRoundingConfig $totalRounding,
    ) {
        $this->taxStatus = $taxStatus;
        $this->itemRounding = $itemRounding;
        $this->totalRounding = $totalRounding;
    }
}
