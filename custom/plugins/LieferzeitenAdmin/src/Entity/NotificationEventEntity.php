<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class NotificationEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $eventKey;
    protected string $triggerKey;
    protected string $channel;
    protected ?string $externalOrderId = null;
    protected ?string $sourceSystem = null;

    /** @var array<string,mixed> */
    protected array $payload = [];

    protected string $status;
    protected ?\DateTimeInterface $dispatchedAt = null;

    public function getEventKey(): string { return $this->eventKey; }
    public function setEventKey(string $eventKey): void { $this->eventKey = $eventKey; }
    public function getTriggerKey(): string { return $this->triggerKey; }
    public function setTriggerKey(string $triggerKey): void { $this->triggerKey = $triggerKey; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $channel): void { $this->channel = $channel; }
    public function getExternalOrderId(): ?string { return $this->externalOrderId; }
    public function setExternalOrderId(?string $externalOrderId): void { $this->externalOrderId = $externalOrderId; }
    public function getSourceSystem(): ?string { return $this->sourceSystem; }
    public function setSourceSystem(?string $sourceSystem): void { $this->sourceSystem = $sourceSystem; }
    /** @return array<string,mixed> */
    public function getPayload(): array { return $this->payload; }
    /** @param array<string,mixed> $payload */
    public function setPayload(array $payload): void { $this->payload = $payload; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function getDispatchedAt(): ?\DateTimeInterface { return $this->dispatchedAt; }
    public function setDispatchedAt(?\DateTimeInterface $dispatchedAt): void { $this->dispatchedAt = $dispatchedAt; }
}
