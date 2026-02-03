<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\RelativeStockChange;

use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockChangeCalculator;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImportLocation;
use Shopware\Core\Framework\Context;

class RelativeStockChangeCalculator implements StockChangeCalculator
{
    public function calculateStockChange(
        array $normalizedRow,
        string $productId,
        StockImportLocation $stockImportLocation,
        JsonApiErrors $errors,
        Context $context,
    ): int {
        return $normalizedRow['change'] ?? 0;
    }
}
