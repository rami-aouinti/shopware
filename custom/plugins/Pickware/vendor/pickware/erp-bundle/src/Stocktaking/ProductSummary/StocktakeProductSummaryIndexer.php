<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ProductSummary;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class StocktakeProductSummaryIndexer extends EntityIndexer
{
    public const NAME = 'PickwareStocktaking.StocktakeProductSummaryIndexer';

    private ProductDefinition $productDefinition;
    private IteratorFactory $iteratorFactory;
    private StocktakeProductSummaryCalculator $stocktakeProductSummaryCalculator;
    private Connection $connection;

    public function __construct(
        ProductDefinition $productDefinition,
        IteratorFactory $iteratorFactory,
        StocktakeProductSummaryCalculator $stocktakeProductSummaryCalculator,
        Connection $connection,
    ) {
        $this->productDefinition = $productDefinition;
        $this->iteratorFactory = $iteratorFactory;
        $this->stocktakeProductSummaryCalculator = $stocktakeProductSummaryCalculator;
        $this->connection = $connection;
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
        // Keeping the indexed entities in sync is done by synchronous subscribers. No need for updates here.
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $productIds = $message->getData();
        $productIds = array_unique(array_filter($productIds));
        if (empty($productIds)) {
            return;
        }

        $productStocktakeCombinations = $this->connection->fetchAllAssociative(
            'SELECT
                HEX(countingProcessItem.`product_id`) as productId,
                HEX(stocktake.`id`) as stocktakeId
            FROM pickware_erp_stocktaking_stocktake stocktake
                LEFT JOIN pickware_erp_stocktaking_stocktake_counting_process countingProcess
                    ON stocktake.`id` = countingProcess.`stocktake_id`
                LEFT JOIN pickware_erp_stocktaking_stocktake_counting_process_item countingProcessItem
                    ON countingProcess.`id` = countingProcessItem.`counting_process_id`
            WHERE
                  stocktake.`is_active` = 1
                  AND countingProcessItem.`product_id` IN (:productIds)',
            ['productIds' => array_map('hex2bin', $productIds)],
            ['productIds' => ArrayParameterType::STRING],
        );

        $this->stocktakeProductSummaryCalculator->recalculateStocktakeProductSummaries(
            array_unique(array_column($productStocktakeCombinations, 'productId')),
            array_unique(array_column($productStocktakeCombinations, 'stocktakeId')),
        );
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
