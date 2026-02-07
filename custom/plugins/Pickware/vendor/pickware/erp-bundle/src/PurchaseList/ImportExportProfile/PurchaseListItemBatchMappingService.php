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

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemEntity;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Throwable;

class PurchaseListItemBatchMappingService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CurrencyFormatter $currencyFormatter,
    ) {}

    /**
     * @param ImmutableCollection<PurchaseListItemImportRow> $normalizedRows
     */
    public function mapRows(ImmutableCollection $normalizedRows, Context $context): PurchaseListItemMappingResult
    {
        $result = PurchaseListItemMappingResult::empty();

        /** @var array<string> $productNumbers */
        $productNumbers = $normalizedRows->map(fn(PurchaseListItemImportRow $row) => $row->getProductNumber())->asArray();
        /** @var array<string> $supplierNames */
        $supplierNames = $normalizedRows
            ->filter(fn(PurchaseListItemImportRow $row) => $row->getSupplierName() !== null)
            ->map(fn(PurchaseListItemImportRow $row) => $row->getSupplierName())
            ->asArray();

        /** @var array<string, ProductEntity> $productsByNumber */
        $productsByNumber = $this->getProductsByNumbers(array_unique($productNumbers), $context);
        /** @var array<string, SupplierEntity> $suppliersByName */
        $suppliersByName = $this->getSuppliersByNames(array_unique($supplierNames), $context);
        $existingConfigurations = $this->getExistingConfigurations(
            array_values($productsByNumber),
            array_values($suppliersByName),
            $context,
        );
        /** @var array<string, ProductSupplierConfigurationEntity> $defaultSupplierConfigurations */
        $defaultSupplierConfigurations = $this->getDefaultSupplierConfigurations(array_values($productsByNumber), $context);
        /** @var array<string, string> $existingPurchaseListItemIds */
        $existingPurchaseListItemIds = $this->getExistingPurchaseListItemIds(array_values($productsByNumber), $context);

        foreach ($normalizedRows as $normalizedRow) {
            try {
                $this->mapRow(
                    $normalizedRow,
                    $productsByNumber,
                    $suppliersByName,
                    $existingConfigurations,
                    $defaultSupplierConfigurations,
                    $existingPurchaseListItemIds,
                    $result,
                    $context,
                );
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $normalizedRow->getRowNumber());
            }
        }

        return $result;
    }

    /**
     * @param array<string> $productNumbers
     * @return array<string, ProductEntity> productNumber => ProductEntity
     */
    private function getProductsByNumbers(array $productNumbers, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productNumber', $productNumbers));
        $criteria->addAssociation('pickwareErpPickwareProduct.defaultSupplier');
        $criteria->addAssociation('tax');
        $products = $this->entityManager->findBy(ProductDefinition::class, $criteria, $context);

        $result = [];
        foreach ($products as $product) {
            /** @var ProductEntity $product */
            $result[$product->getProductNumber()] = $product;
        }

        return $result;
    }

    /**
     * @param array<string> $supplierNames
     * @return array<string, SupplierEntity> supplierName => SupplierEntity
     */
    private function getSuppliersByNames(array $supplierNames, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $supplierNames));
        $suppliers = $this->entityManager->findBy(SupplierDefinition::class, $criteria, $context);

        $result = [];
        foreach ($suppliers as $supplier) {
            /** @var SupplierEntity $supplier */
            $result[$supplier->getName()] = $supplier;
        }

        return $result;
    }

    /**
     * @param array<ProductEntity> $products
     * @param array<SupplierEntity> $suppliers
     */
    private function getExistingConfigurations(array $products, array $suppliers, Context $context): ProductSupplierConfigurationCollection
    {
        $productIds = array_map(fn($product) => $product->getId(), $products);
        $supplierIds = array_map(fn($supplier) => $supplier->getId(), $suppliers);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        $criteria->addFilter(new EqualsAnyFilter('supplierId', $supplierIds));

        /** @var ProductSupplierConfigurationCollection */
        return $this->entityManager->findBy(ProductSupplierConfigurationDefinition::class, $criteria, $context);
    }

    /**
     * @param array<ProductEntity> $products
     * @return array<string, ProductSupplierConfigurationEntity> productId => ProductSupplierConfigurationEntity
     */
    private function getDefaultSupplierConfigurations(array $products, Context $context): array
    {
        $productIdsWithDefaultSupplier = [];
        $supplierIds = [];

        foreach ($products as $product) {
            /** @var PickwareProductEntity|null $pickwareProduct */
            $pickwareProduct = $product->getExtension('pickwareErpPickwareProduct');
            if ($pickwareProduct && $pickwareProduct->getDefaultSupplierId()) {
                $productIdsWithDefaultSupplier[$product->getId()] = $pickwareProduct->getDefaultSupplierId();
                $supplierIds[] = $pickwareProduct->getDefaultSupplierId();
            }
        }

        if (empty($productIdsWithDefaultSupplier)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', array_keys($productIdsWithDefaultSupplier)));
        $criteria->addFilter(new EqualsAnyFilter('supplierId', array_unique($supplierIds)));
        $configurations = $this->entityManager->findBy(
            ProductSupplierConfigurationDefinition::class,
            $criteria,
            $context,
            ['supplier'],
        );

        $result = [];
        foreach ($configurations as $config) {
            /** @var ProductSupplierConfigurationEntity $config */
            $result[$config->getProductId()] = $config;
        }

        return $result;
    }

    /**
     * @param array<ProductEntity> $products
     * @return array<string, string> productId => purchaseListItemId
     */
    private function getExistingPurchaseListItemIds(array $products, Context $context): array
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_map(fn($product) => $product->getId(), $products);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        $purchaseListItems = $this->entityManager->findBy(PurchaseListItemDefinition::class, $criteria, $context);

        $result = [];
        foreach ($purchaseListItems as $item) {
            /** @var PurchaseListItemEntity $item */
            $result[$item->getProductId()] = $item->getId();
        }

        return $result;
    }

    /**
     * @param array<string, ProductEntity> $productsByNumber
     * @param array<string, SupplierEntity> $suppliersByName
     * @param array<string, ProductSupplierConfigurationEntity> $defaultSupplierConfigurations
     * @param array<string, string> $existingPurchaseListItemIds productId => purchaseListItemId
     */
    private function mapRow(
        PurchaseListItemImportRow $row,
        array $productsByNumber,
        array $suppliersByName,
        ProductSupplierConfigurationCollection $existingProductSupplierConfigurations,
        array $defaultSupplierConfigurations,
        array $existingPurchaseListItemIds,
        PurchaseListItemMappingResult $result,
        Context $context,
    ): void {
        $productNumber = $row->getProductNumber();
        $supplierName = $row->getSupplierName();
        $quantity = $row->getQuantity();
        $purchasePriceNet = $row->getPurchasePriceNet();

        if (!isset($productsByNumber[$productNumber])) {
            $result->addPurchaseListImportMessage(
                PurchaseListImportMessage::createProductNotFoundError($productNumber, $row->getRowNumber()),
            );

            return;
        }

        $product = $productsByNumber[$productNumber];
        $productId = $product->getId();

        $productSupplierConfiguration = null;
        if ($supplierName !== null && isset($suppliersByName[$supplierName])) {
            $supplier = $suppliersByName[$supplierName];
            $productSupplierConfiguration = $existingProductSupplierConfigurations->getByProductIdAndSupplierId($productId, $supplier->getId());
        }

        if (isset($existingPurchaseListItemIds[$productId])) {
            // Product already on purchase list, update values only if they are not null no deletion
            $purchaseListImportListItem = new PurchaseListImportListItem(
                id: $existingPurchaseListItemIds[$productId],
                productId: $productId,
                productSupplierConfigurationId: $productSupplierConfiguration?->getId(),
                quantity: $quantity > 0 ? $quantity : null,
                purchasePriceNet: $purchasePriceNet,
                productTaxRate: $product->getTax()?->getTaxRate(),
            );

            if ($purchasePriceNet !== null) {
                $formattedPrice = $this->currencyFormatter->formatCurrencyByLanguage(
                    $purchasePriceNet,
                    'EUR',
                    $context->getLanguageId(),
                    $context,
                );

                $result->addPurchaseListImportMessage(
                    PurchaseListImportMessage::createPurchasePriceUpdateInfo(
                        $productNumber,
                        $formattedPrice,
                        $row->getRowNumber(),
                    ),
                );
            }

            $result->addPurchaseListImportItem($purchaseListImportListItem);

            return;
        }

        // Product not on purchase list, create new entry
        $purchaseListImportListItem = new PurchaseListImportListItem(
            id: Uuid::randomHex(),
            productId: $productId,
            productSupplierConfigurationId: $productSupplierConfiguration?->getId(),
            quantity: $quantity > 0 ? $quantity : null,
            purchasePriceNet: $purchasePriceNet,
            productTaxRate: $product->getTax()?->getTaxRate(),
        );

        // Supplier set in csv but not found in system => error
        if ($supplierName !== null && !isset($suppliersByName[$supplierName])) {
            $result->addPurchaseListImportMessage(
                PurchaseListImportMessage::createSupplierNotFoundError($supplierName, $row->getRowNumber()),
            );

            return;
        }

        // Supplier set in csv but not product supplier configuration found => error
        if ($supplierName !== null && $productSupplierConfiguration === null) {
            $result->addPurchaseListImportMessage(
                PurchaseListImportMessage::createProductSupplierConfigurationNotFoundError(
                    $productNumber,
                    $supplierName,
                    $row->getRowNumber(),
                ),
            );

            return;
        }

        // Supplier not set in csv but default supplier configuration exists => use default supplier
        if ($supplierName === null && isset($defaultSupplierConfigurations[$productId])) {
            $productSupplierConfiguration = $defaultSupplierConfigurations[$productId];
            $purchaseListImportListItem->setProductSupplierConfigurationId($productSupplierConfiguration->getId());

            $result->addPurchaseListImportMessage(
                PurchaseListImportMessage::createUsingDefaultSupplierInfo(
                    $productNumber,
                    $productSupplierConfiguration->getSupplier()->getName(),
                    $row->getRowNumber(),
                ),
            );
        }

        // Supplier not set in csv and no default supplier configuration found => error
        if ($supplierName === null && $productSupplierConfiguration === null) {
            $result->addPurchaseListImportMessage(
                PurchaseListImportMessage::createProductSupplierConfigurationNotFoundError(
                    $productNumber,
                    'Standardlieferant',
                    $row->getRowNumber(),
                ),
            );

            return;
        }

        // Quantity not in CSV => use min purchase quantity
        if ($quantity === null) {
            $quantity = $productSupplierConfiguration->getMinPurchase();
            $purchaseListImportListItem->setQuantity($quantity);

            $result->addPurchaseListImportMessage(PurchaseListImportMessage::createUsingDefaultQuantityInfo(
                $productNumber,
                $quantity,
                $row->getRowNumber(),
            ));
        }

        // Purchase price not in CSV => use first purchase price from supplier configuration
        if ($purchasePriceNet === null) {
            $purchasePrices = $productSupplierConfiguration->getPurchasePrices();
            $purchaseListImportListItem->setPurchasePrices($purchasePrices);

            $formattedPrice = $this->currencyFormatter->formatCurrencyByLanguage(
                $purchasePrices->first()->getNet(),
                'EUR',
                $context->getLanguageId(),
                $context,
            );

            $result->addPurchaseListImportMessage(
                PurchaseListImportMessage::createUsingDefaultPurchasePriceInfo(
                    $productNumber,
                    $formattedPrice,
                    $row->getRowNumber(),
                ),
            );
        }

        $result->addPurchaseListImportItem($purchaseListImportListItem);
    }
}
