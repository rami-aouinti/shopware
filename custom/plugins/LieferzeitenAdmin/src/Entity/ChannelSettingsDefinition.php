<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class ChannelSettingsDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_channel_settings';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ChannelSettingsEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChannelSettingsCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('sales_channel_id', 'salesChannelId'))->addFlags(new Required()),
            new StringField('default_status', 'defaultStatus'),
            (new BoolField('enable_notifications', 'enableNotifications'))->addFlags(new Required()),
            new IntField('shipping_working_days', 'shippingWorkingDays'),
            new StringField('shipping_cutoff', 'shippingCutoff'),
            new IntField('delivery_working_days', 'deliveryWorkingDays'),
            new StringField('delivery_cutoff', 'deliveryCutoff'),
            new StringField('last_changed_by', 'lastChangedBy'),
            new DateTimeField('last_changed_at', 'lastChangedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
