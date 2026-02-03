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

use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Pickware\PickwareErpStarter\ImportExport\TranslatedMessage;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SupplierOrderImportMessage implements TranslatedMessage
{
    private function __construct(
        private readonly ImportExportLogLevel $level,
        private readonly array $content,
        private readonly array $meta,
    ) {
        // Use static constructors instead
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getLevel(): ImportExportLogLevel
    {
        return $this->level;
    }

    public static function createNewProductSupplierConfigurationCreatedInfo(
        string $productNumber,
        string $supplierName,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Für das Produkt %s wurde durch den CSV-Import eine Produkt-Lieferanten-Zuordnung zum Lieferanten %s erstellt.',
                    $productNumber,
                    $supplierName,
                ),
                'en-GB' => sprintf(
                    'A product-supplier mapping to the supplier %s was created for the product %s by the CSV import.',
                    $supplierName,
                    $productNumber,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'supplierName' => $supplierName,
            ],
        );
    }

    public static function createPriceOfProductSupplierConfigurationUpdatedInfo(
        string $productNumber,
        string $supplierName,
        string $price,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Der Preis der Bestellposition %s wurde für die Produkt-Lieferanten-Zuordnung zum Lieferanten %s durch den CSV-Import auf %s aktualisiert.',
                    $productNumber,
                    $supplierName,
                    $price,
                ),
                'en-GB' => sprintf(
                    'The price of the line item %s has been updated to %s for the product-supplier mapping to the supplier %s by the CSV import.',
                    $productNumber,
                    $price,
                    $supplierName,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'supplierName' => $supplierName,
                'price' => $price,
            ],
        );
    }

    public static function createFallbackPriceForLineItemWasUsedInfo(
        string $productNumber,
        string $formattedPrice,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Zur Bestellposition %s konnte kein Einkaufspreis ermittelt werden. Die Position wurde mit einem Preis von %s importiert. Bitte pflege den Preis manuell nach!',
                    $productNumber,
                    $formattedPrice,
                ),
                'en-GB' => sprintf(
                    'No purchase price could be determined for the order line item %s. The line item has been imported with a price of %s. Please add a price manually!',
                    $productNumber,
                    $formattedPrice,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
            ],
        );
    }

    public static function createMultipleProductsWithSameProductSupplierNumberAddedInfo(
        string $supplierName,
        string $supplierProductNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Dem Lieferanten %s sind mehrere Produkte mit der Lieferantenproduktnummer %s zugewiesen. Alle Produkte mit der angegebenen Lieferantenproduktnummer wurden zur Lieferantenbestellung hinzugefügt.',
                    $supplierName,
                    $supplierProductNumber,
                ),
                'en-GB' => sprintf(
                    'Several products with the supplier product number %s are assigned to the supplier %s. All products with the specified supplier product number have been added to the supplier order.',
                    $supplierProductNumber,
                    $supplierName,
                ),
            ],
            meta: [
                'supplierName' => $supplierName,
                'supplierProductName' => $supplierProductNumber,
            ],
        );
    }

    public static function createNewProductSupplierConfigurationCannotBeCreatedBecauseFeatureFlagNotActiveError(
        string $productNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Error,
            content: [
                'de-DE' => sprintf(
                    'Für das Produkt %s kann keine neue Produkt-Lieferanten-Zuordnung erstellt werden, da das Feature mehrere Lieferanten pro Produkt nicht aktiv ist.',
                    $productNumber,
                ),
                'en-GB' => sprintf(
                    'A new product-supplier mapping cannot be created for the product %s because the feature multiple suppliers per product is not active.',
                    $productNumber,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
            ],
        );
    }

    public static function createProductNotFoundError(?string $productNumber, ?string $supplierProductNumber): self
    {
        $productNumberSet = $productNumber !== null && $productNumber !== '';
        $supplierProductNumberSet = $supplierProductNumber !== null && $supplierProductNumber !== '';
        if ($productNumberSet && $supplierProductNumberSet) {
            $content = [
                'en-GB' => sprintf('No product was found for the specified product number "%s" or supplier product number "%s".', $productNumber, $supplierProductNumber),
                'de-DE' => sprintf('Zur angegebenen Produktnummer "%s" oder Lieferantenproduktnummer "%s" konnte kein Produkt gefunden werden.', $productNumber, $supplierProductNumber),
            ];
        } elseif ($productNumberSet) {
            $content = [
                'en-GB' => sprintf('No product was found for the specified product number "%s".', $productNumber),
                'de-DE' => sprintf('Zur angegebenen Produktnummer "%s" konnte kein Produkt gefunden werden.', $productNumber),
            ];
        } elseif ($supplierProductNumberSet) {
            $content = [
                'en-GB' => sprintf('No product was found for the specified supplier product number "%s".', $supplierProductNumber),
                'de-DE' => sprintf('Zur angegebenen Lieferantenproduktnummer "%s" konnte kein Produkt gefunden werden.', $supplierProductNumber),
            ];
        } else {
            $content = [
                'en-GB' => 'The product could not be found, because neither a product number nor a supplier product number was specified.',
                'de-DE' => 'Das Produkt konnte nicht gefunden werden, da weder eine Produktnummer, noch eine Lieferantenproduktnummer angegeben wurde.',
            ];
        }

        return new self(
            level: ImportExportLogLevel::Error,
            content: $content,
            meta: [
                'productNumber' => $productNumber,
                'supplierProductNumber' => $supplierProductNumber,
            ],
        );
    }

    public static function createManufacturerNotFoundError(string $manufacturerName): self
    {
        return new self(
            level: ImportExportLogLevel::Error,
            content: [
                'en-GB' => sprintf('The manufacturer with the name "%s" could not be found.', $manufacturerName),
                'de-DE' => sprintf('Der Hersteller mit dem Namen "%s" konnte nicht gefunden werden.', $manufacturerName),
            ],
            meta: [
                'manufacturerName' => $manufacturerName,
            ],
        );
    }
}
