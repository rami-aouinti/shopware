<?php declare(strict_types=1);

namespace ExternalOrders\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;

class ExternalOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'external_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ExternalOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ExternalOrderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('external_id', 'externalId'))->addFlags(new Required()),
            (new JsonField('payload', 'payload'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
