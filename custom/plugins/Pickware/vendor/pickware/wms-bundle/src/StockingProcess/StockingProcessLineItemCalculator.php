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
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Batch\BatchFeatureService;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareWms\Stocking\WmsStockingStrategy;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessEntity;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessLineItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;

class StockingProcessLineItemCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly WmsStockingStrategy $stockingStrategy,
        private readonly FeatureFlagService $featureFlagService,
        // Might be null with old ERP versions
        private readonly ?BatchFeatureService $batchFeatureService = null,
    ) {}

    public function recalculateStockingProcessLineItems(string $stockingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockingProcessId, $context): void {
            $this->entityManager->deleteByCriteria(
                StockingProcessLineItemDefinition::class,
                ['stockingProcessId' => $stockingProcessId],
                $context,
            );

            /** @var StockingProcessEntity $stockingProcess */
            $stockingProcess = $this->entityManager->getByPrimaryKey(
                StockingProcessDefinition::class,
                $stockingProcessId,
                $context,
                [
                    'sources.goodsReceipt.stocks.batchMappings',
                    'sources.stockContainer.stocks.batchMappings',
                ],
            );

            $productsToStock = $stockingProcess->getSources()->getProductQuantities();
            $stockingRequest = new StockingRequest(
                productQuantities: $productsToStock,
                stockArea: StockArea::warehouse($stockingProcess->getWarehouseId()),
            );

            // WMS might use a non `ProductOrthogonalStockingStrategy` that's why we lock here to ensure concurrent
            // requests still produce their expected stocking solutions
            $this->entityManager->lockPessimistically(
                ProductDefinition::class,
                ['id' => $productsToStock->getProductIds()->asArray()],
                $context,
            );
            $stockingSolution = $this->stockingStrategy->calculateStockingSolution($stockingRequest, $context);

            $lineItemsPayload = [];
            if ($this->isBatchManagementEnabled()) {
                foreach ($stockingSolution->asBatchQuantityLocations() as $index => $batchQuantityLocation) {
                    $lineItemsPayload[] = array_merge(
                        [
                            'stockingProcessId' => $stockingProcessId,
                            'productId' => $batchQuantityLocation->getProductId(),
                            'batchId' => $batchQuantityLocation->getBatchId(),
                            'quantity' => $batchQuantityLocation->getQuantity(),
                            'position' => $index + 1,
                        ],
                        $batchQuantityLocation->getLocation()->toPayload(),
                    );
                }
            } else {
                foreach ($stockingSolution as $index => $productQuantityLocation) {
                    $lineItemsPayload[] = array_merge(
                        [
                            'stockingProcessId' => $stockingProcessId,
                            'productId' => $productQuantityLocation->getProductId(),
                            'quantity' => $productQuantityLocation->getQuantity(),
                            'position' => $index + 1,
                        ],
                        $productQuantityLocation->getStockLocationReference()->toPayload(),
                    );
                }
            }

            $this->entityManager->create(
                StockingProcessLineItemDefinition::class,
                $lineItemsPayload,
                $context,
            );
        });
    }

    private function isBatchManagementEnabled(): bool
    {
        return $this->batchFeatureService?->isBatchManagementAvailable()
            && class_exists(BatchManagementProdFeatureFlag::class)
            && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME);
    }
}
