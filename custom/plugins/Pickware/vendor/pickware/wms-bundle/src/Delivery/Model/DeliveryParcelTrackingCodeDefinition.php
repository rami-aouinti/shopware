<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class DeliveryParcelTrackingCodeDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_delivery_parcel_tracking_code';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField(
                'delivery_parcel_id',
                'deliveryParcelId',
                DeliveryParcelDefinition::class,
            ))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('deliveryParcel', 'delivery_parcel_id', DeliveryParcelDefinition::class, 'id'),

            (new FkField(
                'tracking_code_id',
                'trackingCodeId',
                TrackingCodeDefinition::class,
            ))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('trackingCode', 'tracking_code_id', TrackingCodeDefinition::class, 'id'),
        ]);
    }
}
