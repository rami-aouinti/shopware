<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Indexer;

use Pickware\PickwareErpStarter\Product\PickwareProductInitializer;
use Pickware\PickwareErpStarter\SupplierOrder\IncomingStockUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class IncomingStockIndexer extends EntityIndexer
{
    public const NAME = 'PickwareErp.IncomingStockIndexer';

    private ProductDefinition $productDefinition;
    private IteratorFactory $iteratorFactory;
    private PickwareProductInitializer $pickwareProductInitializer;
    private IncomingStockUpdater $incomingStockUpdater;

    public function __construct(
        ProductDefinition $productDefinition,
        IteratorFactory $iteratorFactory,
        PickwareProductInitializer $pickwareProductInitializer,
        IncomingStockUpdater $incomingStockUpdater,
    ) {
        $this->productDefinition = $productDefinition;
        $this->iteratorFactory = $iteratorFactory;
        $this->pickwareProductInitializer = $pickwareProductInitializer;
        $this->incomingStockUpdater = $incomingStockUpdater;
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
        // Keeping the stock index in sync is done by synchronous subscribers. See IncomingStockUpdater
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $productIds = $message->getData();
        $productIds = array_unique(array_filter($productIds));
        if (empty($productIds)) {
            return;
        }

        $this->pickwareProductInitializer->ensurePickwareProductsExist($productIds);
        $this->incomingStockUpdater->recalculateIncomingStock($productIds);
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
