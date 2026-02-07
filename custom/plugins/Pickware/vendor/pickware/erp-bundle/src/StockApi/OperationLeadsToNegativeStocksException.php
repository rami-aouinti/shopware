<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;

class OperationLeadsToNegativeStocksException extends StockMovementServiceValidationException
{
    public function __construct(
        private readonly StockCollection $negativeStocks,
        private readonly StockLocationConfigurations $stockLocationConfigurations,
    ) {
        $englishDescription = $this->negativeStocks->map(
            fn(StockEntity $stock) => sprintf(
                '%s (product: %s)',
                $this->stockLocationConfigurations->getForStockLocation(
                    $stock->getProductQuantityLocation()->getStockLocationReference(),
                )->getGlobalUniqueDisplayName()->getEnglish(),
                $stock->getProduct()->getProductNumber(),
            ),
        );
        $germanDescription = $this->negativeStocks->map(
            fn(StockEntity $stock) => sprintf(
                '%s (Produkt: %s)',
                $this->stockLocationConfigurations->getForStockLocation(
                    $stock->getProductQuantityLocation()->getStockLocationReference(),
                )->getGlobalUniqueDisplayName()->getGerman(),
                $stock->getProduct()->getProductNumber(),
            ),
        );

        $jsonApiError = new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_NAMESPACE . 'INSUFFICIENT_STOCK_FOR_STOCK_MOVEMENT',
            'title' => 'Operation leads to negative stocks',
            'detail' => [
                'de' => sprintf(
                    'Die Bestandsbewegung kann nicht durchgeführt werden, da sie an folgenden Orten zu einem ' .
                    'negativen Bestand führen würde: %s.',
                    implode(', ', $germanDescription),
                ),
                'en' => sprintf(
                    'The stock movement cannot be performed because it would lead to a negative stock at the ' .
                    'following locations: %s.',
                    implode(', ', $englishDescription),
                ),
            ],
            'meta' => [
                'negativeStocks' => $this->negativeStocks->getProductQuantityLocations(),
            ],
        ]);

        parent::__construct($jsonApiError);
    }

    public function getProductIds(): array
    {
        return $this->negativeStocks
            ->getProductQuantityLocations()
            ->map(fn(ProductQuantityLocation $productQuantityLocation) => $productQuantityLocation->getProductId())
            ->deduplicate()
            ->asArray();
    }
}
