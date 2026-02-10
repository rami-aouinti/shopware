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
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class PositionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PositionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PositionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('position_number', 'positionNumber'))->addFlags(new Required()),
            new StringField('article_number', 'articleNumber'),
            new StringField('status', 'status'),
            new DateTimeField('ordered_at', 'orderedAt'),
            new LongTextField('comment', 'comment'),
            new LongTextField('current_comment', 'currentComment'),
            new DateTimeField('additional_delivery_request_at', 'additionalDeliveryRequestAt'),
            new StringField('additional_delivery_request_initiator', 'additionalDeliveryRequestInitiator'),
            new FkField('paket_id', 'paketId', PaketDefinition::class),
            new StringField('last_changed_by', 'lastChangedBy'),
            new DateTimeField('last_changed_at', 'lastChangedAt'),
            new ManyToOneAssociationField('paket', 'paket_id', PaketDefinition::class, 'id', false),
            new OneToManyAssociationField(
                'lieferterminLieferantHistories',
                LieferterminLieferantHistoryDefinition::class,
                'position_id'
            ),
            new OneToManyAssociationField(
                'neuerLieferterminHistories',
                NeuerLieferterminHistoryDefinition::class,
                'position_id'
            ),
            new OneToManyAssociationField(
                'sendenummerHistories',
                SendenummerHistoryDefinition::class,
                'position_id'
            ),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
