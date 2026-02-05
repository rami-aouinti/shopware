<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\OrderPosition;

use LieferzeitenManagement\Core\Content\DateHistory\LieferzeitenDateHistoryDefinition;
use LieferzeitenManagement\Core\Content\PackagePosition\LieferzeitenPackagePositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\System\User\UserDefinition;

class LieferzeitenOrderPositionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_order_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenOrderPositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenOrderPositionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('order_id', 'orderId', OrderDefinition::class),
            new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class),
            new StringField('san6_order_number', 'san6OrderNumber'),
            new StringField('san6_position_number', 'san6PositionNumber'),
            new IntField('quantity', 'quantity'),
            new DateField('supplier_delivery_start', 'supplierDeliveryStart'),
            new DateField('supplier_delivery_end', 'supplierDeliveryEnd'),
            new StringField('supplier_delivery_comment', 'supplierDeliveryComment'),
            new FkField('supplier_delivery_updated_by_id', 'supplierDeliveryUpdatedById', UserDefinition::class),
            new DateTimeField('supplier_delivery_updated_at', 'supplierDeliveryUpdatedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, 'id', false),
            new ManyToOneAssociationField('supplierDeliveryUpdatedBy', 'supplier_delivery_updated_by_id', UserDefinition::class, 'id', false),
            new OneToManyAssociationField('packagePositions', LieferzeitenPackagePositionDefinition::class, 'order_position_id'),
            new OneToManyAssociationField('dateHistories', LieferzeitenDateHistoryDefinition::class, 'order_position_id'),
        ]);
    }
}
