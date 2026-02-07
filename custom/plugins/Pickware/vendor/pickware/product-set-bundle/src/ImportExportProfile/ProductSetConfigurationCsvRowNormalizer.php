<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\ImportExportProfile;

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;

class ProductSetConfigurationCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct()
    {
        $mapping = new CsvRowNormalizedColumnMapping([
            'productSetProductNumber' => [
                'setArticleOrderNumber', // SW5 migration
                'product number product set',
                'productnumber product set',
                'produktnummer stückliste',
                'produkt nummer stückliste',
            ],
            'productSetConfigurationProductNumber' => [
                'subArticleOrderNumber', // SW5 migration
                'product number assigned product',
                'productnumber assigned product',
                'produktnummer zugeordnetes produkt',
                'produkt nummer zugeordnetes produkt',
            ],
            'quantity' => [
                'quantity',
                'menge',
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
        if (isset($row['quantity']) && self::isIntegerString($row['quantity'])) {
            $row['quantity'] = (int)$row['quantity'];
        } elseif (isset($row['quantity']) && $row['quantity'] === '') {
            $row['quantity'] = null;
        }

        return $row;
    }
}
