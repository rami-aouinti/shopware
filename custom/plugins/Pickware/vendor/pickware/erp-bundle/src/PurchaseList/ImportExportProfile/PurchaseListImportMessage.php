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

use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Pickware\PickwareErpStarter\ImportExport\TranslatedMessage;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PurchaseListImportMessage implements TranslatedMessage
{
    /**
     * @param array{'de-DE': string, 'en-GB': string} $content
     * @param array<string, mixed> $meta
     */
    private function __construct(
        private readonly ImportExportLogLevel $level,
        private readonly array $content,
        private readonly array $meta,
        private readonly int $rowNumber,
    ) {
        // Use static constructors instead
    }

    /**
     * @return array{'de-DE': string, 'en-GB': string}
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getLevel(): ImportExportLogLevel
    {
        return $this->level;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public static function createProductNotFoundError(string $productNumber, int $rowNumber): self
    {
        return new self(
            level: ImportExportLogLevel::Error,
            content: [
                'de-DE' => sprintf(
                    'Das Produkt mit der Produktnummer %s wurde nicht gefunden.',
                    $productNumber,
                ),
                'en-GB' => sprintf(
                    'Product with product number %s was not found.',
                    $productNumber,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
            ],
            rowNumber: $rowNumber,
        );
    }

    public static function createSupplierNotFoundError(string $supplierName, int $rowNumber): self
    {
        return new self(
            level: ImportExportLogLevel::Error,
            content: [
                'de-DE' => sprintf(
                    'Der Lieferant %s wurde nicht gefunden.',
                    $supplierName,
                ),
                'en-GB' => sprintf(
                    'Supplier %s was not found.',
                    $supplierName,
                ),
            ],
            meta: [
                'supplierName' => $supplierName,
            ],
            rowNumber: $rowNumber,
        );
    }

    public static function createProductSupplierConfigurationNotFoundError(
        string $productNumber,
        string $supplierName,
        int $rowNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Error,
            content: [
                'de-DE' => sprintf(
                    'Für das Produkt %s existiert keine Produkt-Lieferanten-Zuordnung zum Lieferanten %s.',
                    $productNumber,
                    $supplierName,
                ),
                'en-GB' => sprintf(
                    'No product supplier configuration exists for product %s and supplier %s.',
                    $productNumber,
                    $supplierName,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'supplierName' => $supplierName,
            ],
            rowNumber: $rowNumber,
        );
    }

    public static function createPurchasePriceUpdateInfo(
        string $productNumber,
        string $formatedPurchasePriceNet,
        int $rowNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Der Einkaufspreis für das Produkt %s wurde auf %s (netto) aktualisiert.',
                    $productNumber,
                    $formatedPurchasePriceNet,
                ),
                'en-GB' => sprintf(
                    'Purchase price for product %s was updated to %s (net).',
                    $productNumber,
                    $formatedPurchasePriceNet,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'purchasePriceNet' => $formatedPurchasePriceNet,
            ],
            rowNumber: $rowNumber,
        );
    }

    public static function createUsingDefaultSupplierInfo(
        string $productNumber,
        string $supplierName,
        int $rowNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Für das Produkt %s wird der Standardlieferant %s verwendet.',
                    $productNumber,
                    $supplierName,
                ),
                'en-GB' => sprintf(
                    'Using default supplier %s for product %s.',
                    $supplierName,
                    $productNumber,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'supplierName' => $supplierName,
            ],
            rowNumber: $rowNumber,
        );
    }

    public static function createUsingDefaultQuantityInfo(
        string $productNumber,
        int $quantity,
        int $rowNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Für das Produkt %s wird die Mindestbestellmenge von %d verwendet.',
                    $productNumber,
                    $quantity,
                ),
                'en-GB' => sprintf(
                    'Using minimum purchase quantity of %d for product %s.',
                    $quantity,
                    $productNumber,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'quantity' => $quantity,
            ],
            rowNumber: $rowNumber,
        );
    }

    public static function createUsingDefaultPurchasePriceInfo(
        string $productNumber,
        string $formatedPrice,
        int $rowNumber,
    ): self {
        return new self(
            level: ImportExportLogLevel::Info,
            content: [
                'de-DE' => sprintf(
                    'Für das Produkt %s wird der Standard Einkaufspreis von %s verwendet.',
                    $productNumber,
                    $formatedPrice,
                ),
                'en-GB' => sprintf(
                    'Using default purchase price of %s for product %s.',
                    $formatedPrice,
                    $productNumber,
                ),
            ],
            meta: [
                'productNumber' => $productNumber,
                'price' => $formatedPrice,
            ],
            rowNumber: $rowNumber,
        );
    }
}
