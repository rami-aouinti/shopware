<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class TaskAssignmentRuleEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $status = null;

    protected ?string $triggerKey = null;

    protected ?string $ruleId = null;

    protected ?string $assigneeType = null;

    protected ?string $assigneeIdentifier = null;

    protected ?int $priority = null;

    protected bool $active = false;

    protected ?array $conditions = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getTriggerKey(): ?string
    {
        return $this->triggerKey;
    }

    public function setTriggerKey(?string $triggerKey): void
    {
        $this->triggerKey = $triggerKey;
    }

    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    public function setRuleId(?string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getAssigneeType(): ?string
    {
        return $this->assigneeType;
    }

    public function setAssigneeType(?string $assigneeType): void
    {
        $this->assigneeType = $assigneeType;
    }

    public function getAssigneeIdentifier(): ?string
    {
        return $this->assigneeIdentifier;
    }

    public function setAssigneeIdentifier(?string $assigneeIdentifier): void
    {
        $this->assigneeIdentifier = $assigneeIdentifier;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): void
    {
        $this->priority = $priority;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    public function setConditions(?array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getLastChangedBy(): ?string
    {
        return $this->lastChangedBy;
    }

    public function setLastChangedBy(?string $lastChangedBy): void
    {
        $this->lastChangedBy = $lastChangedBy;
    }

    public function getLastChangedAt(): ?\DateTimeInterface
    {
        return $this->lastChangedAt;
    }

    public function setLastChangedAt(?\DateTimeInterface $lastChangedAt): void
    {
        $this->lastChangedAt = $lastChangedAt;
    }
}
