<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\ImportExportProfile;

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\BooleanColumnInputParser;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;

class ProductSupplierConfigurationListItemImportCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct(
        private readonly BooleanColumnInputParser $booleanColumnInputParser,
    ) {
        $mapping = new CsvRowNormalizedColumnMapping([
            'productNumber' => [
                'produktnummer',
                'product number',
            ],
            'productName' => [
                'produktname',
                'product name',
            ],
            'gtin' => [
                'gtin',
                'ean',
            ],
            'manufacturer' => [
                'hersteller',
                'manufacturer',
            ],
            'manufacturerProductNumber' => [
                'herstellernummer',
                'manufacturer product number',
            ],
            'supplier' => [
                'lieferant',
                'supplier',
            ],
            'supplierNumber' => [
                'lieferantennummer',
                'supplier number',
            ],
            'supplierProductNumber' => [
                'lieferantenproduktnummer',
                'supplier product number',
            ],
            'minPurchase' => [
                'mindestabnahme',
                'minimum purchase',
            ],
            'purchaseSteps' => [
                'abnahmeintervall',
                'purchase steps',
            ],
            'deliveryTimeDays' => [
                'lieferzeit',
                'delivery time',
            ],
            'purchasePriceNet' => [
                'ek (netto)',
                'pp (net)',
            ],
            'currency' => [
                'währung',
                'currency',
            ],
            'defaultSupplier' => [
                'standardlieferant',
                'default supplier',
            ],
            'delete' => [
                'delete',
                'delete configuration',
                'zuordnung löschen',
                'löschen',
            ],
        ]);
        parent::__construct($mapping);
    }

    public function normalizeRow(array $row): array
    {
        $row = parent::normalizeRow($row);

        $row = $this->mapAliases($row);
        $row = $this->mapTypes($row);

        return $row;
    }

    private function mapAliases(array $row): array
    {
        $this->booleanColumnInputParser->parseBooleanColumnOfRow($row, 'defaultSupplier');
        $this->booleanColumnInputParser->parseBooleanColumnOfRow($row, 'delete');

        return $row;
    }

    private function mapTypes(array $row): array
    {
        if (isset($row['purchasePriceNet']) && is_numeric($row['purchasePriceNet'])) {
            $row['purchasePriceNet'] = (double) $row['purchasePriceNet'];
        }

        if (isset($row['minPurchase']) && self::isIntegerString($row['minPurchase'])) {
            $row['minPurchase'] = (int) $row['minPurchase'];
        }

        if (isset($row['purchaseSteps']) && self::isIntegerString($row['purchaseSteps'])) {
            $row['purchaseSteps'] = (int) $row['purchaseSteps'];
        }

        if (isset($row['deliveryTimeDays']) && self::isIntegerString($row['deliveryTimeDays'])) {
            $row['deliveryTimeDays'] = (int) $row['deliveryTimeDays'];
        }

        if (isset($row['defaultSupplier'])) {
            $row['defaultSupplier'] = $row['defaultSupplier'] === 'true';
        }

        if (isset($row['delete'])) {
            $row['delete'] = $row['delete'] === 'true';
        }

        return $row;
    }
}
