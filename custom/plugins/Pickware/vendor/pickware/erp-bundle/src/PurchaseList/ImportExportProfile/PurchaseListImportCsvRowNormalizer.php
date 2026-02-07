<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\ImportExportProfile;

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;

/**
 * @phpstan-type NormalizedPurchaseListRow array{
 *      productNumber: string,
 *      supplierName?: string|null,
 *      quantity?: int|null,
 *      purchasePriceNet?: float|null
 *  }
 */
class PurchaseListImportCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct()
    {
        $mapping = new CsvRowNormalizedColumnMapping([
            'productNumber' => [
                'produktnummer',
                'product number',
            ],
            'supplierName' => [
                'lieferant',
                'supplier',
            ],
            'quantity' => [
                'bestellmenge',
                'order quantity',
            ],
            'purchasePriceNet' => [
                'ek (netto)',
                'pp (net)',
            ],
        ]);
        parent::__construct($mapping);
    }

    /**
     * @param array<string, ?string> $row
     * @return NormalizedPurchaseListRow
     */
    public function normalizeRow(array $row): array
    {
        $row = parent::normalizeRow($row);

        $row = $this->mapTypes($row);

        return $row;
    }

    /**
     * @param array<string, ?string> $row
     * @return NormalizedPurchaseListRow
     */
    private function mapTypes(array $row): array
    {
        if (isset($row['quantity']) && self::isIntegerString($row['quantity'])) {
            $row['quantity'] = (int)$row['quantity'];
        } elseif (isset($row['quantity']) && $row['quantity'] === '') {
            unset($row['quantity']);
        }

        if (isset($row['purchasePriceNet']) && self::isFloatString($row['purchasePriceNet'])) {
            $row['purchasePriceNet'] = round((float)$row['purchasePriceNet'], 2, PHP_ROUND_HALF_UP);
        } elseif (isset($row['purchasePriceNet']) && $row['purchasePriceNet'] === '') {
            unset($row['purchasePriceNet']);
        }

        if (isset($row['supplierName']) && $row['supplierName'] === '') {
            unset($row['supplierName']);
        }

        return $row;
    }
}
