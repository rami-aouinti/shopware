<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\DateHistory;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionDefinition;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\System\User\UserDefinition;

class LieferzeitenDateHistoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_date_history';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenDateHistoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenDateHistoryEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('order_position_id', 'orderPositionId', LieferzeitenOrderPositionDefinition::class),
            new FkField('package_id', 'packageId', LieferzeitenPackageDefinition::class),
            new StringField('type', 'type'),
            new DateField('range_start', 'rangeStart'),
            new DateField('range_end', 'rangeEnd'),
            new LongTextField('comment', 'comment'),
            new FkField('created_by_id', 'createdById', UserDefinition::class),
            new CreatedAtField(),
            new ManyToOneAssociationField('orderPosition', 'order_position_id', LieferzeitenOrderPositionDefinition::class, 'id', false),
            new ManyToOneAssociationField('package', 'package_id', LieferzeitenPackageDefinition::class, 'id', false),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
        ]);
    }
}
