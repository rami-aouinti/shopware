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

use Shopware\Core\Framework\Context;

class ParcelShippedEvent
{
    private string $orderId;
    private Context $context;

    /**
     * @var TrackingCode[] $trackingCodes
     */
    private array $trackingCodes;

    /**
     * @var ProductQuantityLocation[] $shippedProductQuantityLocations
     */
    private array $shippedProductQuantityLocations;

    /**
     * @param ProductQuantityLocation[] $shippedProductQuantityLocations
     * @param TrackingCode[] $trackingCodes
     */
    public function __construct(
        array $shippedProductQuantityLocations,
        string $orderId,
        array $trackingCodes,
        Context $context,
    ) {
        $this->shippedProductQuantityLocations = $shippedProductQuantityLocations;
        $this->orderId = $orderId;
        $this->trackingCodes = $trackingCodes;
        $this->context = $context;
    }

    public function getShippedProductQuantityLocations(): array
    {
        return $this->shippedProductQuantityLocations;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getTrackingCodes(): array
    {
        return $this->trackingCodes;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
