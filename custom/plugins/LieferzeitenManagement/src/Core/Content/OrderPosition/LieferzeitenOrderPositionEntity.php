<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\OrderPosition;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use LieferzeitenManagement\Core\Content\DateHistory\LieferzeitenDateHistoryCollection;
use LieferzeitenManagement\Core\Content\PackagePosition\LieferzeitenPackagePositionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenOrderPositionEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;

    protected ?string $orderLineItemId = null;

    protected ?string $san6OrderNumber = null;

    protected ?string $san6PositionNumber = null;

    protected ?int $quantity = null;

    protected ?\DateTimeInterface $supplierDeliveryStart = null;

    protected ?\DateTimeInterface $supplierDeliveryEnd = null;

    protected ?string $supplierDeliveryComment = null;

    protected ?string $supplierDeliveryUpdatedById = null;

    protected ?\DateTimeInterface $supplierDeliveryUpdatedAt = null;

    protected ?OrderEntity $order = null;

    protected ?OrderLineItemEntity $orderLineItem = null;

    protected ?UserEntity $supplierDeliveryUpdatedBy = null;

    protected ?LieferzeitenPackagePositionCollection $packagePositions = null;

    protected ?LieferzeitenDateHistoryCollection $dateHistories = null;

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderLineItemId(): ?string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(?string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getSan6OrderNumber(): ?string
    {
        return $this->san6OrderNumber;
    }

    public function setSan6OrderNumber(?string $san6OrderNumber): void
    {
        $this->san6OrderNumber = $san6OrderNumber;
    }

    public function getSan6PositionNumber(): ?string
    {
        return $this->san6PositionNumber;
    }

    public function setSan6PositionNumber(?string $san6PositionNumber): void
    {
        $this->san6PositionNumber = $san6PositionNumber;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getSupplierDeliveryStart(): ?\DateTimeInterface
    {
        return $this->supplierDeliveryStart;
    }

    public function setSupplierDeliveryStart(?\DateTimeInterface $supplierDeliveryStart): void
    {
        $this->supplierDeliveryStart = $supplierDeliveryStart;
    }

    public function getSupplierDeliveryEnd(): ?\DateTimeInterface
    {
        return $this->supplierDeliveryEnd;
    }

    public function setSupplierDeliveryEnd(?\DateTimeInterface $supplierDeliveryEnd): void
    {
        $this->supplierDeliveryEnd = $supplierDeliveryEnd;
    }

    public function getSupplierDeliveryComment(): ?string
    {
        return $this->supplierDeliveryComment;
    }

    public function setSupplierDeliveryComment(?string $supplierDeliveryComment): void
    {
        $this->supplierDeliveryComment = $supplierDeliveryComment;
    }

    public function getSupplierDeliveryUpdatedById(): ?string
    {
        return $this->supplierDeliveryUpdatedById;
    }

    public function setSupplierDeliveryUpdatedById(?string $supplierDeliveryUpdatedById): void
    {
        $this->supplierDeliveryUpdatedById = $supplierDeliveryUpdatedById;
    }

    public function getSupplierDeliveryUpdatedAt(): ?\DateTimeInterface
    {
        return $this->supplierDeliveryUpdatedAt;
    }

    public function setSupplierDeliveryUpdatedAt(?\DateTimeInterface $supplierDeliveryUpdatedAt): void
    {
        $this->supplierDeliveryUpdatedAt = $supplierDeliveryUpdatedAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(?OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    public function getSupplierDeliveryUpdatedBy(): ?UserEntity
    {
        return $this->supplierDeliveryUpdatedBy;
    }

    public function setSupplierDeliveryUpdatedBy(?UserEntity $supplierDeliveryUpdatedBy): void
    {
        $this->supplierDeliveryUpdatedBy = $supplierDeliveryUpdatedBy;
    }

    public function getPackagePositions(): ?LieferzeitenPackagePositionCollection
    {
        return $this->packagePositions;
    }

    public function setPackagePositions(?LieferzeitenPackagePositionCollection $packagePositions): void
    {
        $this->packagePositions = $packagePositions;
    }

    public function getDateHistories(): ?LieferzeitenDateHistoryCollection
    {
        return $this->dateHistories;
    }

    public function setDateHistories(?LieferzeitenDateHistoryCollection $dateHistories): void
    {
        $this->dateHistories = $dateHistories;
    }
}
