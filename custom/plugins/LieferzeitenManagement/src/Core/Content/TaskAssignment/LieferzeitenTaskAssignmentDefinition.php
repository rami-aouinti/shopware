<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TaskAssignment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\User\UserDefinition;

class LieferzeitenTaskAssignmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_task_assignment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LieferzeitenTaskAssignmentCollection::class;
    }

    public function getEntityClass(): string
    {
        return LieferzeitenTaskAssignmentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey(), new \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required()),
            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class),
            new StringField('area', 'area'),
            new StringField('task_type', 'taskType'),
            new FkField('assigned_user_id', 'assignedUserId', UserDefinition::class),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('assignedUser', 'assigned_user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
