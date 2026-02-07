<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Task;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionDefinition;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\System\User\UserDefinition;

class LieferzeitenTaskDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_task';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenTaskCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenTaskEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('order_id', 'orderId', OrderDefinition::class),
            new FkField('order_position_id', 'orderPositionId', LieferzeitenOrderPositionDefinition::class),
            new FkField('package_id', 'packageId', LieferzeitenPackageDefinition::class),
            new StringField('type', 'type'),
            new StringField('status', 'status'),
            new FkField('assigned_user_id', 'assignedUserId', UserDefinition::class),
            new DateTimeField('due_date', 'dueDate'),
            new FkField('created_by_id', 'createdById', UserDefinition::class),
            new DateTimeField('completed_at', 'completedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('orderPosition', 'order_position_id', LieferzeitenOrderPositionDefinition::class, 'id', false),
            new ManyToOneAssociationField('package', 'package_id', LieferzeitenPackageDefinition::class, 'id', false),
            new ManyToOneAssociationField('assignedUser', 'assigned_user_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
        ]);
    }
}
