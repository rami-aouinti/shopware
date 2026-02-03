<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use Pickware\ShippingBundle\Notifications\Notification;

class CashOnDeliveryEnabledNotification extends Notification
{
    public const CODE_CASH_ON_DELIVERY_CONSTRAINT_NOT_MET = 'PICKWARE_SHIPPING_BUNDLE__SHIPMENT__COD_CONSTRAINT_NOT_MET';
    public const CODE_PARCEL_PACKING_SKIPPED = 'PICKWARE_SHIPPING_BUNDLE__SHIPMENT__PARCEL_PACKING_SKIPPED';

    public static function cashOnDeliveryLabelAlreadyExists(string $orderNumber): self
    {
        return new self(
            code: self::CODE_CASH_ON_DELIVERY_CONSTRAINT_NOT_MET,
            message: sprintf(
                'For order %s, a cash on delivery label has already been created. ' .
                'Therefore the cash on delivery option has not been enabled.',
                $orderNumber,
            ),
        );
    }

    public static function parcelPackingSkipped(
        string $orderNumber,
    ): self {
        return new self(
            code: self::CODE_PARCEL_PACKING_SKIPPED,
            message: sprintf(
                'Parcel repacking for order %s was skipped because cash on delivery was automatically enabled.',
                $orderNumber,
            ),
        );
    }
}
