<?php declare(strict_types=1);

namespace ExternalOrders\Entity;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ExternalOrderDataDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'external_order_data';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ExternalOrderDataEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ExternalOrderDataCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new StringField('external_id', 'externalId'))->addFlags(new Required()),
            new StringField('channel', 'channel'),
            (new JsonField('raw_payload', 'rawPayload'))->addFlags(new Required()),
            new StringField('source_status', 'sourceStatus'),
            new DateTimeField('source_created_at', 'sourceCreatedAt'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
