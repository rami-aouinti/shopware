<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;

trait StockLocationReferenceTrait
{
    public function getStockLocationReference(): StockLocationReference
    {
        return match ($this->getLocationTypeTechnicalName()) {
            LocationTypeDefinition::TECHNICAL_NAME_ORDER =>
                StockLocationReference::order($this->getOrderId()),
            LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER =>
                StockLocationReference::returnOrder($this->getReturnOrderId()),
            LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION =>
                StockLocationReference::binLocation($this->getBinLocationId()),
            LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE =>
                StockLocationReference::warehouse($this->getWarehouseId()),
            LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER =>
                StockLocationReference::stockContainer($this->getStockContainerId()),
            LocationTypeDefinition::TECHNICAL_NAME_GOODS_RECEIPT =>
                StockLocationReference::goodsReceipt($this->getGoodsReceiptId()),
            LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION =>
                StockLocationReference::specialStockLocation($this->getSpecialStockLocationTechnicalName()),
        };
    }

    public function getProductQuantityLocations(): ProductQuantityLocationImmutableCollection
    {
        $stockCollection = match ($this->getLocationTypeTechnicalName()) {
            LocationTypeDefinition::TECHNICAL_NAME_ORDER =>
                $this->getOrder()->getExtension('pickwareErpStocks') ?? throw new AssociationNotLoadedException('pickwareErpStocks', $this->getOrder()),
            LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER =>
                $this->getReturnOrder()->getStocks(),
            LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION =>
                $this->getBinLocation()->getStocks(),
            LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE =>
                $this->getWarehouse()->getStocks(),
            LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER =>
                $this->getStockContainer()->getStocks(),
            LocationTypeDefinition::TECHNICAL_NAME_GOODS_RECEIPT =>
                $this->getGoodsReceipt()->getStocks(),
            LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION =>
                $this->getSpecialStockLocation()->getStocks(),
        };

        return $stockCollection->getProductQuantityLocations();
    }
}
