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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProperty\Model\PickingPropertyDeliveryRecordDefinition;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition as OrderDocumentDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

/**
 * @extends EntityDefinition<DeliveryEntity>
 */
class DeliveryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_delivery';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new FkField('picking_process_id', 'pickingProcessId', PickingProcessDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('pickingProcess', 'picking_process_id', PickingProcessDefinition::class, 'id'),

            // Should not be created with orderId NULL. But orders can be deleted while concluded deliveries still exist.
            new FkField('order_id', 'orderId', OrderDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),

            (new StateMachineStateField(
                'state_id',
                'stateId',
                DeliveryStateMachine::TECHNICAL_NAME,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class, 'id'),

            (new OneToManyAssociationField(
                'lineItems',
                DeliveryLineItemDefinition::class,
                'delivery_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            new FkField('stock_container_id', 'stockContainerId', StockContainerDefinition::class),
            new OneToOneAssociationField(
                'stockContainer',
                'stock_container_id',
                'id',
                StockContainerDefinition::class,
                false,
            ),

            (new OneToManyAssociationField(
                'parcels',
                DeliveryParcelDefinition::class,
                'delivery_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'pickingPropertyRecords',
                PickingPropertyDeliveryRecordDefinition::class,
                'delivery_id',
            ))->addFlags(new CascadeDelete(cloneRelevant: false)),

            // A ManyToManyAssociationField is used even though this actually is a many-to-one association. This is
            // because we have a mapping table and with ManyToManyAssociationFields we can make that mapping table
            // transparent for users of the DAL and the API. The many-to-one characteristics are enforced by a unique
            // index on the mapping table.
            (new ManyToManyAssociationField(
                'orderDocuments',
                OrderDocumentDefinition::class,
                DeliveryOrderDocumentMappingDefinition::class,
                'delivery_id',
                'order_document_id',
            ))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField(
                'documents',
                DocumentDefinition::class,
                DeliveryDocumentMappingDefinition::class,
                'delivery_id',
                'document_id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'lifecycleEvents',
                DeliveryLifecycleEventDefinition::class,
                'delivery_reference_id',
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return DeliveryCollection::class;
    }

    public function getEntityClass(): string
    {
        return DeliveryEntity::class;
    }
}
