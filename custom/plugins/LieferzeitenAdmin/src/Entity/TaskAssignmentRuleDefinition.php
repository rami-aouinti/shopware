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
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class TaskAssignmentRuleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lieferzeiten_task_assignment_rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return TaskAssignmentRuleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return TaskAssignmentRuleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('status', 'status'),
            new StringField('trigger_key', 'triggerKey'),
            new StringField('rule_id', 'ruleId'),
            new StringField('assignee_type', 'assigneeType'),
            new StringField('assignee_identifier', 'assigneeIdentifier'),
            new IntField('priority', 'priority'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new JsonField('conditions', 'conditions'),
            new StringField('last_changed_by', 'lastChangedBy'),
            new DateTimeField('last_changed_at', 'lastChangedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
