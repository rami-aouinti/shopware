<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class NotificationToggleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_notification_toggle';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return NotificationToggleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return NotificationToggleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('code', 'code'))->addFlags(new Required()),
            (new BoolField('enabled', 'enabled'))->addFlags(new Required()),
            new StringField('last_changed_by', 'lastChangedBy'),
            new DateTimeField('last_changed_at', 'lastChangedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
