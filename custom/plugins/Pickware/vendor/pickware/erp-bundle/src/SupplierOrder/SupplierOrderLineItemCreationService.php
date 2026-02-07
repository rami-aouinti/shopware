<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

class SupplierOrderLineItemCreationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
    ) {}

    /**
     * @param SupplierOrderLineItemPayloadCreationInput[] $inputs
     */
    public function createSupplierOrderLineItemPayloads(array $inputs, Context $context): SupplierOrderLineItemPayloadCollection
    {
        $productSupplierConfigurationIds = array_map(
            fn(SupplierOrderLineItemPayloadCreationInput $input) => $input->getProductSupplierConfigurationId(),
            $inputs,
        );

        /** @var ProductSupplierConfigurationCollection $productSupplierConfigurations */
        $productSupplierConfigurations = $context->enableInheritance(fn(Context $context) => $this->entityManager->findBy(
            ProductSupplierConfigurationDefinition::class,
            ['id' => $productSupplierConfigurationIds],
            $context,
            [
                'product',
                'product.tax',
            ],
        ));

        $productIds = array_values(array_map(
            fn(ProductSupplierConfigurationEntity $configuration): string => $configuration->getProductId(),
            $productSupplierConfigurations->getElements(),
        ));
        $productNamesById = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

        $payloads = new SupplierOrderLineItemPayloadCollection();
        foreach ($inputs as $input) {
            /** @var ProductSupplierConfigurationEntity $productSupplierConfiguration */
            $productSupplierConfiguration = $productSupplierConfigurations->get($input->getProductSupplierConfigurationId());
            $product = $productSupplierConfiguration->getProduct();

            // Set up a unit price:
            // 1. a price is given via the creation input or
            // 2. purchase price from the product supplier configuration or
            // 3. price = 0.00
            $unitPrice = $input->getUnitPrice() ?? $productSupplierConfiguration->getPurchasePrices()?->getCurrencyPrice(Defaults::CURRENCY)?->getNet() ?? 0;

            $supplierOrderLineItemPayload = [
                ...$this->createPayload(
                    product: $product,
                    productName: $productNamesById[$product->getId()],
                    quantity: $input->getQuantity(),
                    unitPrice: $unitPrice,
                ),
                'productSupplierConfigurationSnapshot' => [
                    'supplierProductNumber' => $productSupplierConfiguration->getSupplierProductNumber(),
                    'minPurchase' => $productSupplierConfiguration->getMinPurchase(),
                    'purchaseSteps' => $productSupplierConfiguration->getPurchaseSteps(),
                ],
                'supplierOrderId' => $input->getSupplierOrderId(),
                ...$input->getAdditionalFields(),
            ];

            $payloads->add(new SupplierOrderLineItemPayload($productSupplierConfiguration->getSupplierId(), $supplierOrderLineItemPayload));
        }

        return $payloads;
    }

    public function createSupplierOrderLineItemPayloadWithoutProductSupplierConfiguration(
        string $productId,
        int $quantity,
        float $unitPrice,
        string $supplierOrderId,
        Context $context,
    ): array {
        /** @var ProductEntity $product */
        $product = $this->entityManager->getByPrimaryKey(ProductDefinition::class, $productId, $context, ['tax']);
        $productNamesById = $this->productNameFormatterService->getFormattedProductNames([$productId], [], $context);

        return [
            ...$this->createPayload(
                product: $product,
                productName: $productNamesById[$product->getId()],
                quantity: $quantity,
                unitPrice: $unitPrice,
            ),
            'supplierOrderId' => $supplierOrderId,
        ];
    }

    private function createPayload(
        ProductEntity $product,
        string $productName,
        int $quantity,
        ?float $unitPrice,
    ): array {
        $taxRate = $product->getTax()?->getTaxRate() || null;
        $taxRules = [];
        if ($taxRate) {
            $taxRules[] = new TaxRule($product->getTax()->getTaxRate(), 100.0);
        }

        return [
            'productId' => $product->getId(),
            'productVersionId' => $product->getVersionId(),
            // The actual price will be calculated by the order recalculation service based on the price definition
            'price' => new CalculatedPrice(
                unitPrice: 0.0,
                totalPrice: 0.0,
                calculatedTaxes: new CalculatedTaxCollection(),
                taxRules: new TaxRuleCollection(),
                quantity: 0,
            ),
            'priceDefinition' => new QuantityPriceDefinition(
                price: $unitPrice ?? 0.0,
                taxRules: new TaxRuleCollection($taxRules),
                quantity: $quantity,
            ),
            'productSnapshot' => [
                'name' => $productName,
                'productNumber' => $product->getProductNumber(),
            ],
            'productSupplierConfigurationSnapshot' => [],
        ];
    }
}
