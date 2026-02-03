<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessEntity;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessSourceEntity;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class StockingProcessDefermentReceiptContentGenerator
{
    private const BARCODE_ACTION_PREFIX = '^C';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly BarcodeGeneratorSVG $barcodeGenerator = new BarcodeGeneratorSVG(),
    ) {}

    /**
     * @return array{
     *  stockingProcessNumber: string,
     *  warehouse: WarehouseEntity,
     *  productNameQuantities: array<array{
     *      productNumber: string,
     *      name: string,
     *      quantity: int
     *  }>,
     *  barcode: string,
     *  localeCode: string
     * }
     */
    public function generateForStockingProcess(
        string $stockingProcessId,
        string $languageId,
        Context $context,
    ): array {
        /** @var StockingProcessEntity $stockingProcess */
        $stockingProcess = $this->entityManager->getByPrimaryKey(
            StockingProcessDefinition::class,
            $stockingProcessId,
            $context,
            [
                'sources.stockContainer.stocks.product',
                'warehouse',
            ],
        );

        /** @var ProductQuantityLocationImmutableCollection $productQuantityLocations */
        $productQuantityLocations = ImmutableCollection::fromArray($stockingProcess->getSources()->getElements())
                ->flatMap(
                    fn(StockingProcessSourceEntity $element) => $element->getProductQuantityLocations(),
                    ProductQuantityLocationImmutableCollection::class,
                );

        $productIds = $productQuantityLocations
            ->groupByProductId()
            ->getProductIds()
            ->asArray();

        $productNamesById = $this->productNameFormatterService->getFormattedProductNames(
            $productIds,
            [],
            $context,
        );

        $productNumbersById = [];
        foreach ($stockingProcess->getSources() as $source) {
            if ($source->getStockContainerId()) {
                $stockContainer = $source->getStockContainer();
                foreach ($stockContainer->getStocks() as $stock) {
                    $product = $stock->getProduct();
                    $productNumbersById[$product->getId()] ??= $product->getProductNumber();
                }
            }
        }

        $productNameQuantities = $productQuantityLocations
            ->groupByProductId()
            ->map(fn(ProductQuantity $productQuantityLocation) => [
                'productNumber' => $productNumbersById[$productQuantityLocation->getProductId()],
                'name' => $productNamesById[$productQuantityLocation->getProductId()],
                'quantity' => $productQuantityLocation->getQuantity(),
            ])
            ->asArray();

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);

        return [
            'stockingProcessNumber' => $stockingProcess->getNumber(),
            'warehouse' => $stockingProcess->getWarehouse(),
            'productNameQuantities' => $productNameQuantities,
            'barcode' => $this->generateBarcode(
                $stockingProcess->getNumber(),
            ),
            'localeCode' => $language->getLocale()->getCode(),
        ];
    }

    private function generateBarcode(string $stockingProcessNumber): string
    {
        $code = self::BARCODE_ACTION_PREFIX . $stockingProcessNumber;
        $barcode = $this->barcodeGenerator->getBarcode(
            barcode: $code,
            type: BarcodeGenerator::TYPE_CODE_128,
            widthFactor: 1,
        );

        return 'data:image/svg+xml;base64,' . base64_encode($barcode);
    }
}
