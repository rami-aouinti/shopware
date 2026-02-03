<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Indexer;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchStockUpdater;
use Pickware\PickwareErpStarter\PaperTrail\ErpPaperTrailUri;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\Product\PickwareProductInitializer;
use Pickware\PickwareErpStarter\Stock\ExternalReservedStockUpdater;
use Pickware\PickwareErpStarter\Stock\InternalReservedStockUpdater;
use Pickware\PickwareErpStarter\Stock\ProductStockUpdater;
use Pickware\PickwareErpStarter\Stock\StockNotAvailableForSaleUpdater;
use Pickware\PickwareErpStarter\Stock\WarehouseStockUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StockIndexer extends EntityIndexer
{
    public const NAME = 'PickwareErp.StockIndexer';

    public function __construct(
        private readonly ProductDefinition $productDefinition,
        private readonly IteratorFactory $iteratorFactory,
        private readonly ProductStockUpdater $productStockUpdater,
        private readonly InternalReservedStockUpdater $internalReservedStockUpdater,
        private readonly ExternalReservedStockUpdater $externalReservedStockUpdater,
        private readonly WarehouseStockUpdater $warehouseStockUpdater,
        private readonly BatchStockUpdater $batchStockUpdater,
        private readonly StockNotAvailableForSaleUpdater $stockNotAvailableForSaleUpdater,
        private readonly PickwareProductInitializer $pickwareProductInitializer,
        private readonly ProductStreamUpdater $productStreamUpdater,
        private readonly FeatureFlagService $featureFlagService,
        private readonly PaperTrailUriProvider $paperTrailUriProvider,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
        // The parameter was only introduced with SW 6.6.10, in any previous Versions it will be null
        #[Autowire(param: 'shopware.product_stream.indexing')]
        private readonly ?bool $indexProductStreams = null,
    ) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $offset);
        // Index 50 products per run
        $iterator->getQuery()->setMaxResults(50);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        // Keeping the stock index in sync is done by synchronous subscribers
        // See ProductStockUpdater, InternalReservedStockUpdater, ProductAvailableUpdater
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $productIds = $message->getData();

        $productIds = array_unique(array_filter($productIds));
        if (empty($productIds)) {
            return;
        }

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('stock-indexing-batch'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Stock indexer started processing a batch of products',
            ['productIds' => $productIds],
        );

        $context = $message->getContext();
        $this->productStockUpdater->recalculateStockFromStockMovementsForProducts($productIds, $context);
        $this->productStockUpdater->upsertStockEntriesForDefaultBinLocationsOfProducts($productIds);
        $this->warehouseStockUpdater->calculateWarehouseStockForProducts($productIds, $message->getContext());
        if ($this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)) {
            $this->batchStockUpdater->calculateBatchStockForProducts($productIds);
        }

        // Note: Recalculating the product reserved stock or the stock not available for sale also triggers product
        // available stock recalculation, which also triggers product available flag calculation.
        $this->pickwareProductInitializer->ensurePickwareProductsExist($productIds);
        $this->stockNotAvailableForSaleUpdater->calculateStockNotAvailableForSaleForProducts($productIds, $message->getContext());
        // Both reserved stock fields are system scope write protected, so we need to run this in a system scope context
        $context->scope(Context::SYSTEM_SCOPE, function(Context $systemScopedContext) use ($productIds): void {
            $this->internalReservedStockUpdater->recalculateProductReservedStock($productIds, $systemScopedContext);
            $this->externalReservedStockUpdater->recalculateExternalReservedStock($productIds, $systemScopedContext);
        });

        // Product streams can use the stock as a filter. Because of this we need to update the product stream
        // mappings via the productStreamUpdater to make sure dynamic product groups are updated.
        // For further reference see https://github.com/pickware/shopware-plugins/issues/3232
        // Note: This is only done if the product stream indexing is enabled, so the user can determine if the indexer should run.
        if ($this->indexProductStreams !== false) {
            $this->productStreamUpdater->updateProducts($productIds, $context);
        }

        $this->paperTrailLoggingService->logPaperTrailEvent('Stock indexer finished processing a batch of products');
        $this->paperTrailUriProvider->reset();
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->productDefinition)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }
}
