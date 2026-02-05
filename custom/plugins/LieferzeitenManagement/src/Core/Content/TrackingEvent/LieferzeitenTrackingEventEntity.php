<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TrackingEvent;

use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LieferzeitenTrackingEventEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $trackingNumberId = null;

    protected ?string $status = null;

    protected ?string $description = null;

    protected ?\DateTimeInterface $occurredAt = null;

    protected ?array $payload = null;

    protected ?LieferzeitenTrackingNumberEntity $trackingNumber = null;

    public function getTrackingNumberId(): ?string
    {
        return $this->trackingNumberId;
    }

    public function setTrackingNumberId(?string $trackingNumberId): void
    {
        $this->trackingNumberId = $trackingNumberId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOccurredAt(): ?\DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(?\DateTimeInterface $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getTrackingNumber(): ?LieferzeitenTrackingNumberEntity
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?LieferzeitenTrackingNumberEntity $trackingNumber): void
    {
        $this->trackingNumber = $trackingNumber;
    }
}
