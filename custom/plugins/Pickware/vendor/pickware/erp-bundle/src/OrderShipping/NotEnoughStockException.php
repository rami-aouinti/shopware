<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Throwable;

class NotEnoughStockException extends OrderShippingException
{
    /**
     * @var ProductQuantity[]
     */
    private array $stockShortage;

    /**
     * @param ProductQuantity[] $stockShortage
     */
    public function __construct(
        WarehouseEntity $warehouse,
        OrderEntity $order,
        array $stockShortage,
        ?Throwable $previous = null,
    ) {
        $this->stockShortage = $stockShortage;
        $jsonApiError = new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_NOT_ENOUGH_STOCK,
            'title' => [
                'en' => 'Operation leads to negative stocks',
                'de' => 'Aktion führt zu negativem Bestand',
            ],
            'detail' => [
                'en' => sprintf(
                    'There is not enough stock in warehouse %s (%s) to ship the order %s.',
                    $warehouse->getName(),
                    $warehouse->getCode(),
                    $order->getOrderNumber(),
                ),
                'de' => sprintf(
                    'Es ist nicht genug Bestand im Lager %s (%s) um die Bestellung %s zu versenden.',
                    $warehouse->getName(),
                    $warehouse->getCode(),
                    $order->getOrderNumber(),
                ),
            ],
            'meta' => [
                'warehouseName' => $warehouse->getName(),
                'warehouseCode' => $warehouse->getCode(),
                'orderNumber' => $order->getOrderNumber(),
                'stockShortage' => $stockShortage,
            ],
        ]);

        parent::__construct($jsonApiError, $previous);
    }

    /**
     * @return ProductQuantity[] $stockShortage
     */
    public function getStockShortage(): array
    {
        return $this->stockShortage;
    }
}
