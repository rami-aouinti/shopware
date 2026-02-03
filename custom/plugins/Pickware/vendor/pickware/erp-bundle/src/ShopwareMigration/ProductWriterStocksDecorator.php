<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ShopwareMigration;

use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\TotalStockWriter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use SwagMigrationAssistant\Migration\Writer\WriterInterface;

class ProductWriterStocksDecorator implements WriterInterface
{
    private WriterInterface $decoratedWriter;
    private TotalStockWriter $totalStockWriter;

    public function __construct(
        WriterInterface $decoratedProductWriter,
        TotalStockWriter $totalStockWriter,
    ) {
        $this->decoratedWriter = $decoratedProductWriter;
        $this->totalStockWriter = $totalStockWriter;
    }

    public function supports(): string
    {
        return $this->decoratedWriter->supports();
    }

    public function writeData(array $data, Context $context): array
    {
        $writeResults = $this->decoratedWriter->writeData($data, $context);
        $productWriteResults = $writeResults['product'] ?? [];
        $productStocks = [];
        /** @var EntityWriteResult $productWriteResult */
        foreach ($productWriteResults as $productWriteResult) {
            $payload = $productWriteResult->getPayload();
            // Filter out instances of EntityWriteResult with empty payload. Somehow they are introduced by a bug in
            // the Shopware DAL.
            if (count($payload) === 0) {
                continue;
            }
            if ($payload['versionId'] !== Defaults::LIVE_VERSION) {
                continue;
            }
            if (!array_key_exists('stock', $payload)) {
                continue;
            }

            // Only write positive stocks for each product. Ignore negative stocks here. The product (total) stock will
            // be overwritten to stock = 0 by the product stock updater because we won't write (negative) stock
            // movements here.
            if ($payload['stock'] < 0) {
                continue;
            }

            $productStocks[$payload['id']] = $payload['stock'];
        }
        if (count($productStocks) > 0) {
            $this->totalStockWriter->setTotalStockForProducts(
                $productStocks,
                StockLocationReference::shopwareMigration(),
                $context,
            );
        }

        return $writeResults;
    }
}
