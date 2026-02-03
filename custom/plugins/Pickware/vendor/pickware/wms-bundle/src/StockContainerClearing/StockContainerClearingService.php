<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockContainerClearing;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class StockContainerClearingService
{
    private EntityManager $entityManager;
    private StockMovementService $stockMovementService;

    public function __construct(EntityManager $entityManager, StockMovementService $stockMovementService)
    {
        $this->entityManager = $entityManager;
        $this->stockMovementService = $stockMovementService;
    }

    public function putStockInStockContainersToUnknownLocationInWarehouse(
        array $stockContainerIds,
        string $warehouseId,
        Context $context,
    ): void {
        /** @var StockContainerCollection $stockContainers */
        $stockContainers = $this->entityManager->findBy(
            StockContainerDefinition::class,
            ['id' => $stockContainerIds],
            $context,
            ['stocks'],
        );

        $stockMovements = [];
        foreach ($stockContainers as $stockContainer) {
            $stockMovements[] = array_values(
                $stockContainer
                    ->getStocks()
                    ->map(fn(StockEntity $stock) => StockMovement::create([
                        'id' => Uuid::randomHex(),
                        'productId' => $stock->getProductId(),
                        'quantity' => $stock->getQuantity(),
                        'source' => StockLocationReference::stockContainer($stockContainer->getId()),
                        'destination' => StockLocationReference::warehouse($warehouseId),
                    ])),
            );
        }
        $stockMovements = array_merge(...$stockMovements);

        $this->stockMovementService->moveStock($stockMovements, $context);
    }
}
