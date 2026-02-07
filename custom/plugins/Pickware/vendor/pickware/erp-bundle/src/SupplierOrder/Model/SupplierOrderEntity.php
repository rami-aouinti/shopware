<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemCollection;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\Tag\TagCollection;

class SupplierOrderEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $supplierId;
    protected ?SupplierEntity $supplier = null;
    protected array $supplierSnapshot;
    protected ?string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected array $warehouseSnapshot;
    protected string $currencyId;
    protected CashRoundingConfig $itemRounding;
    protected CashRoundingConfig $totalRounding;
    protected ?CurrencyEntity $currency = null;
    protected string $stateId;
    protected ?StateMachineStateEntity $state = null;
    protected string $paymentStateId;
    protected ?StateMachineStateEntity $paymentState = null;
    protected ?SupplierOrderLineItemCollection $lineItems = null;
    protected string $number;
    protected ?string $supplierOrderNumber;
    protected DateTimeInterface $orderDateTime;
    protected ?DateTimeInterface $dueDate;
    protected ?DateTimeInterface $expectedDeliveryDate;
    protected ?DateTimeInterface $actualDeliveryDate;
    protected CartPrice $price;
    protected float $amountTotal;
    protected float $amountNet;
    protected float $positionPrice;
    protected string $taxStatus;
    protected ?string $internalComment;
    protected ?GoodsReceiptCollection $goodsReceipts = null;
    protected ?GoodsReceiptLineItemCollection $goodsReceiptLineItems = null;
    protected ?array $customFields = null;
    protected ?TagCollection $tags = null;

    public function getSupplierId(): ?string
    {
        return $this->supplierId;
    }

    public function setSupplierId(?string $supplierId): void
    {
        if ($this->supplier && $this->supplier->getId() !== $supplierId) {
            $this->supplier = null;
        }

        $this->supplierId = $supplierId;
    }

    public function getSupplier(): SupplierEntity
    {
        if ($this->supplier == null && $this->supplierId !== null) {
            throw new AssociationNotLoadedException('supplier', $this);
        }

        return $this->supplier;
    }

    public function setSupplier(SupplierEntity $supplier): void
    {
        $this->supplierId = $supplier->getId();
        $this->supplier = $supplier;
    }

    public function getSupplierSnapshot(): array
    {
        return $this->supplierSnapshot;
    }

    public function setSupplierSnapshot(array $supplierSnapshot): void
    {
        $this->supplierSnapshot = $supplierSnapshot;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?string $warehouseId): void
    {
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseId) {
            $this->warehouse = null;
        }

        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): ?WarehouseEntity
    {
        if ($this->warehouse == null && $this->warehouseId !== null) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(WarehouseEntity $warehouse): void
    {
        $this->warehouseId = $warehouse->getId();
        $this->warehouse = $warehouse;
    }

    public function getWarehouseSnapshot(): array
    {
        return $this->warehouseSnapshot;
    }

    public function setWarehouseSnapshot(array $warehouseSnapshot): void
    {
        $this->warehouseSnapshot = $warehouseSnapshot;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        if ($this->currency && $this->currency->getId() !== $currencyId) {
            $this->currency = null;
        }

        $this->currencyId = $currencyId;
    }

    public function getCurrency(): CurrencyEntity
    {
        if (!$this->currency) {
            throw new AssociationNotLoadedException('currency', $this);
        }

        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currencyId = $currency->getId();
        $this->currency = $currency;
    }

    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function setItemRounding(CashRoundingConfig $itemRounding): void
    {
        $this->itemRounding = $itemRounding;
    }

    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function setTotalRounding(CashRoundingConfig $totalRounding): void
    {
        $this->totalRounding = $totalRounding;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        if ($this->state && $this->state->getId() !== $stateId) {
            $this->state = null;
        }
        $this->stateId = $stateId;
    }

    public function getState(): StateMachineStateEntity
    {
        if (!$this->state) {
            throw new AssociationNotLoadedException('state', $this);
        }

        return $this->state;
    }

    public function setState(StateMachineStateEntity $state): void
    {
        $this->stateId = $state->getId();
        $this->state = $state;
    }

    public function getPaymentStateId(): string
    {
        return $this->paymentStateId;
    }

    public function setPaymentStateId(string $paymentStateId): void
    {
        if ($this->paymentState && $this->paymentState->getId() !== $paymentStateId) {
            $this->paymentState = null;
        }
        $this->paymentStateId = $paymentStateId;
    }

    public function getPaymentState(): StateMachineStateEntity
    {
        if (!$this->paymentState) {
            throw new AssociationNotLoadedException('paymentState', $this);
        }

        return $this->paymentState;
    }

    public function setPaymentState(StateMachineStateEntity $paymentState): void
    {
        $this->paymentStateId = $paymentState->getId();
        $this->paymentState = $paymentState;
    }

    public function getLineItems(): SupplierOrderLineItemCollection
    {
        if (!$this->lineItems) {
            throw new AssociationNotLoadedException('lineItems', $this);
        }

        return $this->lineItems;
    }

    public function setLineItems(SupplierOrderLineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getSupplierOrderNumber(): ?string
    {
        return $this->supplierOrderNumber;
    }

    public function setSupplierOrderNumber(?string $supplierOrderNumber): void
    {
        $this->supplierOrderNumber = $supplierOrderNumber;
    }

    public function getOrderDateTime(): DateTimeInterface
    {
        return $this->orderDateTime;
    }

    public function setOrderDateTime(DateTimeInterface $orderDateTime): void
    {
        $this->orderDateTime = $orderDateTime;
    }

    public function getDueDate(): ?DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function getExpectedDeliveryDate(): ?DateTimeInterface
    {
        return $this->expectedDeliveryDate;
    }

    public function setExpectedDeliveryDate(?DateTimeInterface $expectedDeliveryDate): void
    {
        $this->expectedDeliveryDate = $expectedDeliveryDate;
    }

    public function getActualDeliveryDate(): ?DateTimeInterface
    {
        return $this->actualDeliveryDate;
    }

    public function setActualDeliveryDate(?DateTimeInterface $actualDeliveryDate): void
    {
        $this->actualDeliveryDate = $actualDeliveryDate;
    }

    public function getPrice(): CartPrice
    {
        return $this->price;
    }

    public function setPrice(CartPrice $price): void
    {
        $this->price = $price;
    }

    public function getAmountTotal(): float
    {
        return $this->amountTotal;
    }

    public function setAmountTotal(float $amountTotal): void
    {
        $this->amountTotal = $amountTotal;
    }

    public function getAmountNet(): float
    {
        return $this->amountNet;
    }

    public function setAmountNet(float $amountNet): void
    {
        $this->amountNet = $amountNet;
    }

    public function getPositionPrice(): float
    {
        return $this->positionPrice;
    }

    public function setPositionPrice(float $positionPrice): void
    {
        $this->positionPrice = $positionPrice;
    }

    public function getTaxStatus(): string
    {
        return $this->taxStatus;
    }

    public function setTaxStatus(string $taxStatus): void
    {
        $this->taxStatus = $taxStatus;
    }

    public function getGoodsReceipts(): GoodsReceiptCollection
    {
        if (!$this->goodsReceipts) {
            throw new AssociationNotLoadedException('goodsReceipts', $this);
        }

        return $this->goodsReceipts;
    }

    public function setGoodsReceipts(?GoodsReceiptCollection $goodsReceipts): void
    {
        $this->goodsReceipts = $goodsReceipts;
    }

    public function getGoodsReceiptLineItems(): ?GoodsReceiptLineItemCollection
    {
        if (!$this->goodsReceiptLineItems) {
            throw new AssociationNotLoadedException('goodsReceiptLineItems', $this);
        }

        return $this->goodsReceiptLineItems;
    }

    public function setGoodsReceiptLineItems(?GoodsReceiptLineItemCollection $goodsReceiptLineItems): void
    {
        $this->goodsReceiptLineItems = $goodsReceiptLineItems;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function setInternalComment(?string $internalComment): void
    {
        $this->internalComment = $internalComment;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getTags(): TagCollection
    {
        if ($this->tags === null) {
            throw new AssociationNotLoadedException('tags', $this);
        }

        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }
}
