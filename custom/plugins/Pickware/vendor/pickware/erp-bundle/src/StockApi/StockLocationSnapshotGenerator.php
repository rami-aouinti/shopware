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

use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class StockLocationSnapshotGenerator
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param StockLocationReference[] $stockLocationReferences
     */
    public function generateSnapshots(array $stockLocationReferences, Context $context): void
    {
        foreach ($stockLocationReferences as $stockLocationReference) {
            $this->generateSnapshot($stockLocationReference, $context);
        }
    }

    private function generateSnapshot(StockLocationReference $stockLocationReference, Context $context): void
    {
        switch ($stockLocationReference->getLocationTypeTechnicalName()) {
            case LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION:
                $stockLocationReference->setSnapshot(null);
                break;

            case LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION:
                /** @var BinLocationEntity $binLocation */
                $binLocation = $this->entityManager->getByPrimaryKey(
                    BinLocationDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                    ['warehouse'],
                );
                $snapshot = [
                    'code' => $binLocation->getCode(),
                    'warehouseCode' => $binLocation->getWarehouse()->getCode(),
                    'warehouseName' => $binLocation->getWarehouse()->getName(),
                ];
                $stockLocationReference->setSnapshot($snapshot);
                break;

            case LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE:
                /** @var WarehouseEntity $warehouse */
                $warehouse = $this->entityManager->getByPrimaryKey(
                    WarehouseDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $snapshot = [
                    'code' => $warehouse->getCode(),
                    'name' => $warehouse->getName(),
                ];
                $stockLocationReference->setSnapshot($snapshot);
                break;

            case LocationTypeDefinition::TECHNICAL_NAME_ORDER:
                /** @var OrderEntity $order */
                $order = $this->entityManager->getByPrimaryKey(
                    OrderDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $snapshot = ['orderNumber' => $order->getOrderNumber()];
                $stockLocationReference->setSnapshot($snapshot);
                break;

            case LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER:
                /** @var StockContainerEntity $stockContainer */
                $stockContainer = $this->entityManager->getByPrimaryKey(
                    StockContainerDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $snapshot = [
                    'id' => $stockContainer->getId(),
                ];
                $stockLocationReference->setSnapshot($snapshot);
                break;

            case LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER:
                /** @var ReturnOrderEntity $returnOrder */
                $returnOrder = $this->entityManager->getByPrimaryKey(
                    ReturnOrderDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $snapshot = [
                    'number' => $returnOrder->getNumber(),
                ];
                $stockLocationReference->setSnapshot($snapshot);
                break;

            case LocationTypeDefinition::TECHNICAL_NAME_GOODS_RECEIPT:
                /** @var GoodsReceiptEntity $goodsReceipt */
                $goodsReceipt = $this->entityManager->getByPrimaryKey(
                    GoodsReceiptDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $snapshot = ['number' => $goodsReceipt->getNumber()];
                $stockLocationReference->setSnapshot($snapshot);
                break;

            default:
                throw new LogicException(sprintf(
                    'No snapshot logic defined for location type "%s". Please check if this switch case is exhausting',
                    $stockLocationReference->getLocationTypeTechnicalName(),
                ));
        }
    }
}
