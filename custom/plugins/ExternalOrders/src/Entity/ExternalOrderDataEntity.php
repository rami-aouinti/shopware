<?php declare(strict_types=1);

namespace ExternalOrders\Entity;

use DateTimeInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ExternalOrderDataEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;

    protected string $externalId;

    protected ?string $channel = null;

    /** @var array<mixed> */
    protected array $rawPayload;

    protected ?string $sourceStatus = null;

    protected ?DateTimeInterface $sourceCreatedAt = null;

    protected ?OrderEntity $order = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): void
    {
        $this->channel = $channel;
    }

    /** @return array<mixed> */
    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    /** @param array<mixed> $rawPayload */
    public function setRawPayload(array $rawPayload): void
    {
        $this->rawPayload = $rawPayload;
    }

    public function getSourceStatus(): ?string
    {
        return $this->sourceStatus;
    }

    public function setSourceStatus(?string $sourceStatus): void
    {
        $this->sourceStatus = $sourceStatus;
    }

    public function getSourceCreatedAt(): ?DateTimeInterface
    {
        return $this->sourceCreatedAt;
    }

    public function setSourceCreatedAt(?DateTimeInterface $sourceCreatedAt): void
    {
        $this->sourceCreatedAt = $sourceCreatedAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }
}
