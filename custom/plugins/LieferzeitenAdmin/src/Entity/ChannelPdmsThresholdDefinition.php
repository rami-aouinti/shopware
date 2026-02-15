<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class ChannelPdmsThresholdDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_channel_pdms_threshold';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ChannelPdmsThresholdEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChannelPdmsThresholdCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('sales_channel_id', 'salesChannelId'))->addFlags(new Required()),
            (new StringField('pdms_lieferzeit', 'pdmsLieferzeit'))->addFlags(new Required()),
            (new IntField('shipping_overdue_working_days', 'shippingOverdueWorkingDays'))->addFlags(new Required()),
            (new IntField('delivery_overdue_working_days', 'deliveryOverdueWorkingDays'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
