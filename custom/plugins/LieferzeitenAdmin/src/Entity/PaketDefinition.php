<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class PaketDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_paket';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PaketEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PaketCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('paket_number', 'paketNumber'))->addFlags(new Required()),
            new StringField('status', 'status'),
            new DateTimeField('shipping_date', 'shippingDate'),
            new DateTimeField('delivery_date', 'deliveryDate'),
            new StringField('external_order_id', 'externalOrderId'),
            new StringField('source_system', 'sourceSystem'),
            new StringField('customer_email', 'customerEmail'),
            new StringField('payment_method', 'paymentMethod'),
            new DateTimeField('payment_date', 'paymentDate'),
            new DateTimeField('order_date', 'orderDate'),
            new StringField('base_date_type', 'baseDateType'),
            new DateTimeField('calculated_delivery_date', 'calculatedDeliveryDate'),
            new StringField('sync_badge', 'syncBadge'),
            new JsonField('status_push_queue', 'statusPushQueue'),
            new StringField('last_changed_by', 'lastChangedBy'),
            new DateTimeField('last_changed_at', 'lastChangedAt'),
            new OneToManyAssociationField('positions', PositionDefinition::class, 'paket_id'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
