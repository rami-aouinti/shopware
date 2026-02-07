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

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;

class ProductSupplierConfigurationException extends ImportExportException
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__PRODUCT_SUPPLIER_CONFIGURATION_IMPORTER__';

    public static function createProductNotFoundError(string $productNumber): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Product not found',
                'de' => 'Produkt nicht gefunden',
            ],
            'detail' => [
                'en' => sprintf('The product with the number "%s" could not be found.', $productNumber),
                'de' => sprintf('Das Produkt mit der Nummer "%s" konnte nicht gefunden werden.', $productNumber),
            ],
            'meta' => [
                'productNumber' => $productNumber,
            ],
        ]);
    }

    public static function createSupplierNotFoundByNameError(string $supplierName): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Supplier not found',
                'de' => 'Lieferant nicht gefunden',
            ],
            'detail' => [
                'en' => sprintf('The supplier with the name "%s" could not be found.', $supplierName),
                'de' => sprintf('Der Lieferant mit dem Namen "%s" konnte nicht gefunden werden.', $supplierName),
            ],
            'meta' => [
                'supplierName' => $supplierName,
            ],
        ]);
    }

    public static function createSupplierNotFoundByNumberError(string $supplierNumber): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Supplier not found',
                'de' => 'Lieferant nicht gefunden',
            ],
            'detail' => [
                'en' => sprintf('The supplier with the number "%s" could not be found.', $supplierNumber),
                'de' => sprintf('Der Lieferant mit der Nummer "%s" konnte nicht gefunden werden.', $supplierNumber),
            ],
            'meta' => [
                'supplierNumber' => $supplierNumber,
            ],
        ]);
    }

    public static function createManufacturerNotFoundError(string $manufacturerName): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Manufacturer not found',
                'de' => 'Hersteller nicht gefunden',
            ],
            'detail' => [
                'en' => sprintf('The manufacturer with the name "%s" could not be found.', $manufacturerName),
                'de' => sprintf('Der Hersteller mit dem Namen "%s" konnte nicht gefunden werden.', $manufacturerName),
            ],
            'meta' => [
                'manufacturerName' => $manufacturerName,
            ],
        ]);
    }

    public static function createProductDefaultSupplierColumnPresentError(): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Product default supplier column present',
                'de' => 'Spalte für Standardlieferanten vorhanden',
            ],
            'detail' => [
                'en' => 'The provided CSV contains a column for default suppliers which is not supported.',
                'de' => 'Die bereitgestellte CSV-Datei enthält eine Spalte für Standardlieferanten, die nicht unterstützt wird.',
            ],
        ]);
    }

    public static function createNoSupplierProvidedColumnError(): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'No supplier provided',
                'de' => 'Kein Lieferant angegeben',
            ],
            'detail' => [
                'en' => 'The product supplier mapping properties could not be imported because no supplier was provided.',
                'de' => 'Die Eigenschaften der Produkt-Lieferanten-Zuordnung konnten nicht importiert werden, da kein Lieferant angegeben wurde.',
            ],
        ]);
    }
}
