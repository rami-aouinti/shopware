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
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class NotificationEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_notification_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return NotificationEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return NotificationEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('event_key', 'eventKey'))->addFlags(new Required()),
            (new StringField('trigger_key', 'triggerKey'))->addFlags(new Required()),
            (new StringField('channel', 'channel'))->addFlags(new Required()),
            new StringField('external_order_id', 'externalOrderId'),
            new StringField('source_system', 'sourceSystem'),
            (new JsonField('payload', 'payload'))->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            new DateTimeField('dispatched_at', 'dispatchedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
