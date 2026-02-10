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

class LieferzeitenTaskDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_task';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenTaskEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenTaskCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            new StringField('assignee', 'assignee'),
            new DateTimeField('due_date', 'dueDate'),
            new StringField('initiator', 'initiator'),
            (new JsonField('payload', 'payload'))->addFlags(new Required()),
            new DateTimeField('closed_at', 'closedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}

