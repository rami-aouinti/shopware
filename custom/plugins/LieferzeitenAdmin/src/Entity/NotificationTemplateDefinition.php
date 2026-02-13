<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class NotificationTemplateDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_notification_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return NotificationTemplateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return NotificationTemplateCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('trigger_key', 'triggerKey'))->addFlags(new Required()),
            new StringField('sales_channel_id', 'salesChannelId'),
            new StringField('language_id', 'languageId'),
            (new StringField('subject', 'subject'))->addFlags(new Required()),
            (new LongTextField('content_html', 'contentHtml'))->addFlags(new Required()),
            (new LongTextField('content_plain', 'contentPlain'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
