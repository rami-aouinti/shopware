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

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;

class SupplierImportCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct()
    {
        $mapping = new CsvRowNormalizedColumnMapping([
            'number' => [
                'nummer',
                'number',
            ],
            'name' => [
                'name',
            ],
            'customerNumber' => [
                'kundennummer',
                'customer number',
            ],
            'language' => [
                'sprache',
                'language',
            ],
            'defaultDeliveryTime' => [
                'standardlieferzeit',
                'default delivery time',
            ],
            'title' => [
                'titel',
                'titel (kontaktperson)',
                'title',
                'title (contact person)',
            ],
            'firstName' => [
                'vorname',
                'vorname (kontaktperson)',
                'first name',
                'first name (contact person)',
            ],
            'lastName' => [
                'nachname',
                'nachname (kontaktperson)',
                'last name',
                'last name (contact person)',
            ],
            'email' => [
                'e-mail',
                'e-mail-adresse',
                'email',
                'email address',
            ],
            'phone' => [
                'telefonnummer',
                'phone',
                'phone number',
            ],
            'fax' => [
                'faxnummer',
                'fax number',
            ],
            'website' => [
                'website',
            ],
            'company' => [
                'company',
                'firma',
            ],
            'department' => [
                'department',
                'abteilung',
            ],
            'street' => [
                'street',
                'straße',
            ],
            'houseNumber' => [
                'house number',
                'hausnummer',
            ],
            'addressAddition' => [
                'address addition',
                'addressaddition',
                'addresszusatz',
                'adresszusatz',
            ],
            'zipCode' => [
                'zip code',
                'plz',
            ],
            'city' => [
                'city',
                'stadt',
            ],
            'countryIso' => [
                'country (iso2)',
                'country iso',
                'country',
                'land (iso2)',
                'land iso',
                'land',
            ],
            'vatId' => [
                'vat id',
                'ust id',
            ],
            'comment' => [
                'comment',
                'commentary',
                'kommentar',
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
        if (isset($row['defaultDeliveryTime']) && is_numeric($row['defaultDeliveryTime'])) {
            $row['defaultDeliveryTime'] = (int)$row['defaultDeliveryTime'];
        }

        return $row;
    }
}
