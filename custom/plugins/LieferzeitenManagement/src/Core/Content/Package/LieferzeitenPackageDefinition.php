<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Package;

use LieferzeitenManagement\Core\Content\DateHistory\LieferzeitenDateHistoryDefinition;
use LieferzeitenManagement\Core\Content\PackagePosition\LieferzeitenPackagePositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\System\User\UserDefinition;

class LieferzeitenPackageDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_package';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenPackageCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenPackageEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('order_id', 'orderId', OrderDefinition::class),
            new StringField('san6_package_number', 'san6PackageNumber'),
            new StringField('package_status', 'packageStatus'),
            new DateTimeField('latest_shipping_at', 'latestShippingAt'),
            new DateTimeField('latest_delivery_at', 'latestDeliveryAt'),
            new DateTimeField('shipped_at', 'shippedAt'),
            new DateTimeField('delivered_at', 'deliveredAt'),
            new StringField('tracking_number', 'trackingNumber'),
            new StringField('tracking_provider', 'trackingProvider'),
            new StringField('tracking_status', 'trackingStatus'),
            new DateField('new_delivery_start', 'newDeliveryStart'),
            new DateField('new_delivery_end', 'newDeliveryEnd'),
            new StringField('new_delivery_comment', 'newDeliveryComment'),
            new FkField('new_delivery_updated_by_id', 'newDeliveryUpdatedById', UserDefinition::class),
            new DateTimeField('new_delivery_updated_at', 'newDeliveryUpdatedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('newDeliveryUpdatedBy', 'new_delivery_updated_by_id', UserDefinition::class, 'id', false),
            new OneToManyAssociationField('packagePositions', LieferzeitenPackagePositionDefinition::class, 'package_id'),
            new OneToManyAssociationField('dateHistories', LieferzeitenDateHistoryDefinition::class, 'package_id'),
        ]);
    }
}
