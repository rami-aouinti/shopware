<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model\Extensions;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwareWms\Delivery\Model\DeliveryParcelDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryParcelTrackingCodeDefinition;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class TrackingCodeDeliveryParcelExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ManyToManyAssociationField(
                'pickwareWmsDeliveryParcels',
                DeliveryParcelDefinition::class,
                DeliveryParcelTrackingCodeDefinition::class,
                'tracking_code_id',
                'delivery_parcel_id',
            ))->addFlags(new CascadeDelete(cloneRelevant: false)),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return TrackingCodeDefinition::class;
    }
}
