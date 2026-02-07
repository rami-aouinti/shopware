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

use Pickware\PickwareErpStarter\Stock\ProductSalesUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * The product.sales value is calculated and kept in sync by our StockUpdaterOverride. Why is this indexer necessary?
 * We only recently added the product.sales calculation to our StockUpdaterOverride. Therefore, for most products the
 * product.sales value is missing or wrong. Since fixing these values in a single migration might be too much for the
 * update process to hande.
 * We decided to "migrate" the values by using this indexer. We know that this indexer will be triggered with each
 * upcoming update which will be unnecessary besides the first update that introduces this indexer. We keep this
 * behavior for now and might change it in the future (to globally run it only a single time).
 *
 * See also this issue: https://github.com/pickware/shopware-plugins/issues/2852
 */
class ProductSalesIndexer extends EntityIndexer
{
    public const NAME = 'PickwareErp.ProductSalesIndexer';

    private ProductDefinition $productDefinition;
    private IteratorFactory $iteratorFactory;
    private ProductSalesUpdater $productSalesUpdater;

    public function __construct(
        ProductDefinition $productDefinition,
        IteratorFactory $iteratorFactory,
        ProductSalesUpdater $productSalesUpdater,
    ) {
        $this->productDefinition = $productDefinition;
        $this->iteratorFactory = $iteratorFactory;
        $this->productSalesUpdater = $productSalesUpdater;
    }

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
        // Keeping the sales index in sync is done by synchronous subscribers
        // See StockUpdaterOverride
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $productIds = $message->getData();

        $productIds = array_unique(array_filter($productIds));
        if (empty($productIds)) {
            return;
        }

        $this->productSalesUpdater->updateSales($productIds);
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
