<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class NeuerLieferterminPaketHistoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_neuer_liefertermin_paket_history';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return NeuerLieferterminPaketHistoryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return NeuerLieferterminPaketHistoryCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('paket_id', 'paketId', PaketDefinition::class))->addFlags(new Required()),
            new DateTimeField('liefertermin_from', 'lieferterminFrom'),
            new DateTimeField('liefertermin_to', 'lieferterminTo'),
            new DateTimeField('liefertermin', 'liefertermin'),
            new StringField('last_changed_by', 'lastChangedBy'),
            new DateTimeField('last_changed_at', 'lastChangedAt'),
            new ManyToOneAssociationField('paket', 'paket_id', PaketDefinition::class, 'id', false),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
