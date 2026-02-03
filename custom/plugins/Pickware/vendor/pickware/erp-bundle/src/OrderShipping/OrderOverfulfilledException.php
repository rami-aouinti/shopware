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

class OrderOverfulfilledException extends OrderParcelException
{
    public function __construct(
        private readonly string $orderId,
        private readonly ProductQuantityImmutableCollection $overfulfilledQuantities,
    ) {
        parent::__construct(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_ORDER_OVERFULFILLED,
            'title' => [
                'en' => 'Order is overfulfilled',
                'de' => 'Bestellung ist übererfüllt',
            ],
            'detail' => [
                'en' => 'No more products can be shipped than have been ordered.',
                'de' => 'Es können nicht mehr Produkte versendet werden als bestellt wurden.',
            ],
            'meta' => [
                'orderId' => $orderId,
                'overfulfilledQuantities' => $overfulfilledQuantities,
            ],
        ]));
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOverfulfilledQuantities(): ProductQuantityImmutableCollection
    {
        return $this->overfulfilledQuantities;
    }
}
