<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TrackingEvent;

use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class LieferzeitenTrackingEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_tracking_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenTrackingEventCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenTrackingEventEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('tracking_number_id', 'trackingNumberId', LieferzeitenTrackingNumberDefinition::class),
            new StringField('status', 'status'),
            new StringField('description', 'description'),
            new DateTimeField('occurred_at', 'occurredAt'),
            new JsonField('payload', 'payload'),
            new CreatedAtField(),
            new ManyToOneAssociationField('trackingNumber', 'tracking_number_id', LieferzeitenTrackingNumberDefinition::class, 'id', false),
        ]);
    }
}
