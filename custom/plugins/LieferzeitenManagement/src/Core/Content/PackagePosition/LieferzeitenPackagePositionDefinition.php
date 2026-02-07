<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\PackagePosition;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionDefinition;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class LieferzeitenPackagePositionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_package_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenPackagePositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenPackagePositionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('package_id', 'packageId', LieferzeitenPackageDefinition::class),
            new FkField('order_position_id', 'orderPositionId', LieferzeitenOrderPositionDefinition::class),
            new IntField('quantity', 'quantity'),
            new StringField('split_type', 'splitType'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('package', 'package_id', LieferzeitenPackageDefinition::class, 'id', false),
            new ManyToOneAssociationField('orderPosition', 'order_position_id', LieferzeitenOrderPositionDefinition::class, 'id', false),
        ]);
    }
}
