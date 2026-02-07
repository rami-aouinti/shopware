<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\ImportExportProfile;

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;

class SupplierOrderImportCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct()
    {
        $mapping = new CsvRowNormalizedColumnMapping([
            'productNumber' => [
                'produktnummer',
                'product number',
            ],
            'productName' => [
                'produktname',
                'product name',
            ],
            'ean' => [
                'gtin',
                'ean',
            ],
            'manufacturerName' => [
                'herstellername',
                'manufacturer name',
            ],
            'manufacturerProductNumber' => [
                'herstellernummer',
                'manufacturer product number',
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
            'quantity' => [
                'menge',
                'quantity',
            ],
            'expectedDeliveryDate' => [
                'erwartetes lieferdatum',
                'expected delivery date',
            ],
            'actualDeliveryDate' => [
                'tatsächliches lieferdatum',
                'actual delivery date',
            ],
            // Note that the "unit/total price calculated by tax status" (see export profile) is not used in the import.
            'unitPrice' => [
                'ek (netto)',
                'pp (net)',
            ],
            'totalPrice' => [
                'summe (netto)',
                'total (net)',
            ],
        ]);
        parent::__construct($mapping);
    }

    public function normalizeRow(array $row): array
    {
        $row = parent::normalizeRow($row);

        return $this->mapTypes($row);
    }

    private function mapTypes(array $row): array
    {
        if (isset($row['minPurchase']) && self::isIntegerString($row['minPurchase'])) {
            $row['minPurchase'] = (int)$row['minPurchase'];
        }

        if (isset($row['purchaseSteps']) && self::isIntegerString($row['purchaseSteps'])) {
            $row['purchaseSteps'] = (int)$row['purchaseSteps'];
        }

        if (isset($row['quantity']) && self::isIntegerString($row['quantity'])) {
            $row['quantity'] = (int)$row['quantity'];
        }

        $this->mapPriceToNumber($row, 'unitPrice');
        $this->mapPriceToNumber($row, 'totalPrice');

        return $row;
    }

    public function mapPriceToNumber(array &$data, string $key): void
    {
        if (!isset($data[$key])) {
            return;
        }

        $parsedNumber = preg_replace('/^\\D+|\\D+$/', '', $data[$key]);
        if (is_numeric($parsedNumber)) {
            $data[$key] = (float)$parsedNumber;
        }
    }
}
