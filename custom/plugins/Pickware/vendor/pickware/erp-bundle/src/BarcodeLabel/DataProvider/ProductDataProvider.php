<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel\DataProvider;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelConfiguration;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelLayoutItemFactory;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelLayouts;
use Pickware\PickwareErpStarter\BarcodeLabel\ProductLabelConfiguration;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;

class ProductDataProvider extends AbstractBarcodeLabelDataProvider
{
    public const BARCODE_LABEL_TYPE = 'product';

    public function __construct(
        private readonly BarcodeLabelLayoutItemFactory $barcodeLabelLayoutItemFactory,
        private readonly CurrencyFormatter $currencyFormatter,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly EntityManager $entityManager,
    ) {}

    public function getBarcodeLabelType(): string
    {
        return self::BARCODE_LABEL_TYPE;
    }

    public function getSupportedLayouts(): array
    {
        return [
            BarcodeLabelLayouts::LAYOUT_A,
            BarcodeLabelLayouts::LAYOUT_B,
            BarcodeLabelLayouts::LAYOUT_C,
        ];
    }

    protected function collectLabelData(BarcodeLabelConfiguration $labelConfiguration, Context $context): array
    {
        $productLabelConfigurations = ImmutableCollection::fromArray(
            $labelConfiguration->getDataProviderParamValueByKey('productLabelConfigurations', []),
            ProductLabelConfiguration::class,
        );
        $products = $this->getProducts($productLabelConfigurations, $context);
        $currency = $this->getCurrency($context);
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            $products->getIds(),
            [],
            $context,
        );

        $data = [];
        /** @var ProductLabelConfiguration $productLabelConfiguration */
        foreach ($productLabelConfigurations as $productLabelConfiguration) {
            $product = $products->get($productLabelConfiguration->getProductId());

            $item = match ($labelConfiguration->getLayout()) {
                BarcodeLabelLayouts::LAYOUT_A => $this->barcodeLabelLayoutItemFactory->createItemForLayoutA(
                    $product->getProductNumber(),
                    $product->getProductNumber(),
                ),
                BarcodeLabelLayouts::LAYOUT_B => $this->barcodeLabelLayoutItemFactory->createItemForLayoutB(
                    $product->getProductNumber(),
                    $product->getProductNumber(),
                    $this->currencyFormatter->formatCurrencyByLanguage(
                        $product->getPrice()?->getCurrencyPrice($context->getCurrencyId())?->getGross() ?? 0.00,
                        $currency,
                        $this->getLanguageId($labelConfiguration),
                        $context,
                    ),
                    $productNames[$product->getId()],
                ),
                BarcodeLabelLayouts::LAYOUT_C => $this->barcodeLabelLayoutItemFactory->createItemForLayoutC(
                    $product->getProductNumber(),
                    $productNames[$product->getId()],
                    $product->getProductNumber(),
                ),
            };
            $item['barcodeLabelCount'] = $productLabelConfiguration->getBarcodeLabelCount();
            $data[] = $item;
        }

        return $data;
    }

    /**
     * @param ImmutableCollection<ProductLabelConfiguration> $productLabelConfigurations
     */
    private function getProducts(ImmutableCollection $productLabelConfigurations, Context $context): ProductCollection
    {
        $productIds = $productLabelConfigurations->map(
            fn(ProductLabelConfiguration $labelConfiguration) => $labelConfiguration->getProductId(),
        );

        /**
         * @var ProductCollection $products
         */
        $products = $context->enableInheritance(fn(Context $context) => $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => $productIds->asArray()],
            $context,
        ));

        return $products;
    }

    private function getLanguageId(BarcodeLabelConfiguration $labelConfiguration): string
    {
        $languageId = $labelConfiguration->getDataProviderParamValueByKey('languageId');

        if ($languageId === null) {
            throw new InvalidArgumentException('Parameter "languageId" missing in barcode label configuration.');
        }

        return $languageId;
    }

    private function getCurrency(Context $context): string
    {
        /**
         * @var CurrencyEntity $currency
         */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $context->getCurrencyId(),
            $context,
        );

        if (!$currency->getShortName()) {
            throw ProductDataProviderException::currencyShortNameMissing(
                $currency->getIsoCode(),
                $context->getCurrencyId(),
                $context->getLanguageId(),
            );
        }

        return $currency->getShortName();
    }
}
