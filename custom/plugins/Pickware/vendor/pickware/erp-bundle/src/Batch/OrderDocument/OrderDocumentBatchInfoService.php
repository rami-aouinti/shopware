<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\OrderDocument;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Shopware\Core\Framework\Context;

class OrderDocumentBatchInfoService
{
    private const STOCK_ASSOCIATIONS = [
        'batchMappings.batch',
        'product.pickwareErpPickwareProduct',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @param string[] $orderIds
     * @return array<string, ProductBatchInfoMap> Map of orderId => ProductBatchInfoMap
     */
    public function getBatchInfoByOrders(array $orderIds, Context $context): array
    {
        if (count($orderIds) === 0) {
            return [];
        }

        /** @var StockCollection $stocks */
        $stocks = $this->entityManager->findBy(
            StockDefinition::class,
            ['orderId' => $orderIds],
            $context,
            self::STOCK_ASSOCIATIONS,
        );

        $stocksByOrder = [];
        foreach ($stocks as $stock) {
            $orderId = $stock->getOrderId();
            if ($orderId === null) {
                continue;
            }
            $stocksByOrder[$orderId][] = $stock;
        }

        return array_map(
            fn(array $orderStocks) => $this->buildProductBatchInfoMap(new StockCollection($orderStocks)),
            $stocksByOrder,
        );
    }

    /**
     * @param string[] $stockContainerIds
     * @return array<string, ProductBatchInfoMap> Map of stockContainerId => ProductBatchInfoMap
     */
    public function getBatchInfoByStockContainers(array $stockContainerIds, Context $context): array
    {
        if (count($stockContainerIds) === 0) {
            return [];
        }

        /** @var StockCollection $stocks */
        $stocks = $this->entityManager->findBy(
            StockDefinition::class,
            ['stockContainerId' => $stockContainerIds],
            $context,
            self::STOCK_ASSOCIATIONS,
        );

        $stocksByContainer = [];
        foreach ($stocks as $stock) {
            $stockContainerId = $stock->getStockContainerId();
            if ($stockContainerId === null) {
                continue;
            }
            $stocksByContainer[$stockContainerId][] = $stock;
        }

        return array_map(
            fn(array $containerStocks) => $this->buildProductBatchInfoMap(new StockCollection($containerStocks)),
            $stocksByContainer,
        );
    }

    private function buildProductBatchInfoMap(StockCollection $stocks): ProductBatchInfoMap
    {
        $batchInfoByProduct = [];

        foreach ($stocks as $stock) {
            $productId = $stock->getProductId();
            $batchInfoCollection = $this->extractBatchInfoFromStock($stock);

            if (!isset($batchInfoByProduct[$productId])) {
                $batchInfoByProduct[$productId] = $batchInfoCollection;
            } else {
                $batchInfoByProduct[$productId] = $batchInfoByProduct[$productId]->merge($batchInfoCollection);
            }
        }

        return new ProductBatchInfoMap($batchInfoByProduct);
    }

    private function extractBatchInfoFromStock(StockEntity $stock): OrderDocumentBatchInfoCollection
    {
        /** @var PickwareProductEntity|null $pickwareProduct */
        $pickwareProduct = $stock->getProduct()->getExtension('pickwareErpPickwareProduct');
        $isBatchManaged = $pickwareProduct?->getIsBatchManaged() ?? false;
        if (!$isBatchManaged) {
            return OrderDocumentBatchInfoCollection::create([]);
        }

        $batchInfoItems = [];
        $mappedQuantity = 0;

        foreach ($stock->getBatchMappings() as $batchMapping) {
            $batch = $batchMapping->getBatch();
            $batchInfoItems[] = new OrderDocumentBatchInfo(
                $batch->getNumber(),
                $batch->getBestBeforeDate()?->toIsoString(),
                $batchMapping->getQuantity(),
            );
            $mappedQuantity += $batchMapping->getQuantity();
        }

        $unmappedQuantity = $stock->getQuantity() - $mappedQuantity;
        if ($unmappedQuantity > 0) {
            $batchInfoItems[] = new OrderDocumentBatchInfo(
                batchNumber: null,
                bestBeforeDate: null,
                quantity: $unmappedQuantity,
            );
        }

        return OrderDocumentBatchInfoCollection::create($batchInfoItems);
    }
}
