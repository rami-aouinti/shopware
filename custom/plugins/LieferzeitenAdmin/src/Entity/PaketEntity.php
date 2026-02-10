<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PaketEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $paketNumber = null;

    protected ?string $status = null;

    protected ?\DateTimeInterface $shippingDate = null;

    protected ?\DateTimeInterface $deliveryDate = null;

    protected ?string $externalOrderId = null;

    protected ?string $sourceSystem = null;

    protected ?string $customerEmail = null;

    protected ?string $paymentMethod = null;

    protected ?\DateTimeInterface $paymentDate = null;

    protected ?\DateTimeInterface $orderDate = null;

    protected ?string $baseDateType = null;

    protected ?\DateTimeInterface $calculatedDeliveryDate = null;

    protected ?string $syncBadge = null;

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $statusPushQueue = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    protected ?PositionCollection $positions = null;

    public function getPaketNumber(): ?string
    {
        return $this->paketNumber;
    }

    public function setPaketNumber(?string $paketNumber): void
    {
        $this->paketNumber = $paketNumber;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getShippingDate(): ?\DateTimeInterface
    {
        return $this->shippingDate;
    }

    public function setShippingDate(?\DateTimeInterface $shippingDate): void
    {
        $this->shippingDate = $shippingDate;
    }


    public function getDeliveryDate(): ?\DateTimeInterface
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?\DateTimeInterface $deliveryDate): void
    {
        $this->deliveryDate = $deliveryDate;
    }

    public function getExternalOrderId(): ?string
    {
        return $this->externalOrderId;
    }

    public function setExternalOrderId(?string $externalOrderId): void
    {
        $this->externalOrderId = $externalOrderId;
    }

    public function getSourceSystem(): ?string
    {
        return $this->sourceSystem;
    }

    public function setSourceSystem(?string $sourceSystem): void
    {
        $this->sourceSystem = $sourceSystem;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getOrderDate(): ?\DateTimeInterface
    {
        return $this->orderDate;
    }

    public function setOrderDate(?\DateTimeInterface $orderDate): void
    {
        $this->orderDate = $orderDate;
    }

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(?\DateTimeInterface $paymentDate): void
    {
        $this->paymentDate = $paymentDate;
    }

    public function getBaseDateType(): ?string
    {
        return $this->baseDateType;
    }

    public function setBaseDateType(?string $baseDateType): void
    {
        $this->baseDateType = $baseDateType;
    }

    public function getCalculatedDeliveryDate(): ?\DateTimeInterface
    {
        return $this->calculatedDeliveryDate;
    }

    public function setCalculatedDeliveryDate(?\DateTimeInterface $calculatedDeliveryDate): void
    {
        $this->calculatedDeliveryDate = $calculatedDeliveryDate;
    }

    public function getSyncBadge(): ?string
    {
        return $this->syncBadge;
    }


    /** @return array<int, array<string, mixed>>|null */
    public function getStatusPushQueue(): ?array
    {
        return $this->statusPushQueue;
    }

    /** @param array<int, array<string, mixed>>|null $statusPushQueue */
    public function setStatusPushQueue(?array $statusPushQueue): void
    {
        $this->statusPushQueue = $statusPushQueue;
    }

    public function setSyncBadge(?string $syncBadge): void
    {
        $this->syncBadge = $syncBadge;
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

    public function getPositions(): ?PositionCollection
    {
        return $this->positions;
    }

    public function setPositions(?PositionCollection $positions): void
    {
        $this->positions = $positions;
    }
}
