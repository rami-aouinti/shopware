<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockLocationConfigurations
{
    private array $stockLocationConfigurations = [];

    public function __construct() {}

    public function getForStockLocation(StockLocationReference $stockLocation): StockLocationConfiguration
    {
        return $this->stockLocationConfigurations[$stockLocation->hash()];
    }

    public function addConfiguration(StockLocationReference $stockLocation, StockLocationConfiguration $configuration): void
    {
        $this->stockLocationConfigurations[$stockLocation->hash()] = $configuration;
    }
}
