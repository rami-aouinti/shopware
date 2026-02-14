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


    protected ?string $shippingAssignmentType = null;

    protected ?string $partialShipmentQuantity = null;

    protected ?\DateTimeInterface $businessDateFrom = null;

    protected ?\DateTimeInterface $businessDateTo = null;

    protected ?\DateTimeInterface $calculatedDeliveryDate = null;

    protected ?string $syncBadge = null;

    protected ?bool $isTestOrder = null;

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $statusPushQueue = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    protected ?PositionCollection $positions = null;

    protected ?NeuerLieferterminPaketHistoryCollection $neuerLieferterminPaketHistory = null;

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


    public function getShippingAssignmentType(): ?string
    {
        return $this->shippingAssignmentType;
    }

    public function setShippingAssignmentType(?string $shippingAssignmentType): void
    {
        $this->shippingAssignmentType = $shippingAssignmentType;
    }

    public function getPartialShipmentQuantity(): ?string
    {
        return $this->partialShipmentQuantity;
    }

    public function setPartialShipmentQuantity(?string $partialShipmentQuantity): void
    {
        $this->partialShipmentQuantity = $partialShipmentQuantity;
    }

    public function getBusinessDateFrom(): ?\DateTimeInterface
    {
        return $this->businessDateFrom;
    }

    public function setBusinessDateFrom(?\DateTimeInterface $businessDateFrom): void
    {
        $this->businessDateFrom = $businessDateFrom;
    }

    public function getBusinessDateTo(): ?\DateTimeInterface
    {
        return $this->businessDateTo;
    }

    public function setBusinessDateTo(?\DateTimeInterface $businessDateTo): void
    {
        $this->businessDateTo = $businessDateTo;
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



    public function getIsTestOrder(): ?bool
    {
        return $this->isTestOrder;
    }

    public function setIsTestOrder(?bool $isTestOrder): void
    {
        $this->isTestOrder = $isTestOrder;
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

    public function getNeuerLieferterminPaketHistory(): ?NeuerLieferterminPaketHistoryCollection
    {
        return $this->neuerLieferterminPaketHistory;
    }

    public function setNeuerLieferterminPaketHistory(?NeuerLieferterminPaketHistoryCollection $neuerLieferterminPaketHistory): void
    {
        $this->neuerLieferterminPaketHistory = $neuerLieferterminPaketHistory;
    }
}

