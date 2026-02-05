<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TrackingNumber;

use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use LieferzeitenManagement\Core\Content\TrackingEvent\LieferzeitenTrackingEventDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

class LieferzeitenTrackingNumberDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_tracking_number';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenTrackingNumberCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenTrackingNumberEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('package_id', 'packageId', LieferzeitenPackageDefinition::class),
            new StringField('tracking_number', 'trackingNumber'),
            new StringField('tracking_provider', 'trackingProvider'),
            new BoolField('is_active', 'isActive'),
            new CreatedAtField(),
            new ManyToOneAssociationField('package', 'package_id', LieferzeitenPackageDefinition::class, 'id', false),
            new OneToManyAssociationField('events', LieferzeitenTrackingEventDefinition::class, 'tracking_number_id'),
        ]);
    }
}
