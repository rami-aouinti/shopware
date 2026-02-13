<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LieferzeitenTaskEntity extends Entity
{
    use EntityIdTrait;

    protected string $status = 'open';

    protected ?string $assignee = null;

    protected ?\DateTimeInterface $dueDate = null;

    protected ?string $initiator = null;

    protected ?string $positionId = null;

    protected ?string $triggerKey = null;

    /** @var array<string, mixed> */
    protected array $payload = [];

    protected ?\DateTimeInterface $closedAt = null;

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAssignee(): ?string
    {
        return $this->assignee;
    }

    public function setAssignee(?string $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function getInitiator(): ?string
    {
        return $this->initiator;
    }

    public function setInitiator(?string $initiator): void
    {
        $this->initiator = $initiator;
    }

    public function getPositionId(): ?string
    {
        return $this->positionId;
    }

    public function setPositionId(?string $positionId): void
    {
        $this->positionId = $positionId;
    }

    public function getTriggerKey(): ?string
    {
        return $this->triggerKey;
    }

    public function setTriggerKey(?string $triggerKey): void
    {
        $this->triggerKey = $triggerKey;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): void
    {
        $this->closedAt = $closedAt;
    }
}
