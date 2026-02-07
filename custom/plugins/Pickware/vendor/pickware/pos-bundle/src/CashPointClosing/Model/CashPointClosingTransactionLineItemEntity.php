<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CashPointClosingTransactionLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $cashPointClosingTransactionId;
    protected ?CashPointClosingTransactionEntity $cashPointClosingTransaction = null;
    protected ?string $referencedCashPointClosingTransactionId;
    protected ?CashPointClosingTransactionEntity $referencedCashPointClosingTransaction = null;
    protected ?string $productId = null;
    protected ?string $productVersionId = null;
    protected ?ProductEntity $product = null;
    protected string $type;
    protected string $productNumber;
    protected ?string $gtin;
    protected string $name;
    protected ?string $voucherId;
    protected int $quantity;
    protected array $vatTable;
    protected array $pricePerUnit;
    protected array $total;
    protected ?array $discount;

    public function getCashPointClosingTransactionId(): string
    {
        return $this->cashPointClosingTransactionId;
    }

    public function setCashPointClosingTransactionId(string $cashPointClosingTransactionId): void
    {
        if (
            $this->cashPointClosingTransaction
            && $this->cashPointClosingTransaction->getId() !== $cashPointClosingTransactionId
        ) {
            $this->cashPointClosingTransaction = null;
        }
        $this->cashPointClosingTransactionId = $cashPointClosingTransactionId;
    }

    public function getCashPointClosingTransaction(): CashPointClosingTransactionEntity
    {
        if (!$this->cashPointClosingTransaction) {
            throw new AssociationNotLoadedException('cashPointClosingTransaction', $this);
        }

        return $this->cashPointClosingTransaction;
    }

    public function setCashPointClosingTransaction(CashPointClosingTransactionEntity $cashPointClosingTransaction): void
    {
        $this->cashPointClosingTransaction = $cashPointClosingTransaction;
        $this->cashPointClosingTransactionId = $cashPointClosingTransaction->getId();
    }

    public function getReferencedCashPointClosingTransactionId(): ?string
    {
        return $this->referencedCashPointClosingTransactionId;
    }

    public function setReferencedCashPointClosingTransactionId(?string $referencedCashPointClosingTransactionId): void
    {
        if (
            !$referencedCashPointClosingTransactionId
            || ($this->referencedCashPointClosingTransaction
                && $this->referencedCashPointClosingTransaction->getId() !== $referencedCashPointClosingTransactionId)
        ) {
            $this->product = null;
        }
        $this->referencedCashPointClosingTransactionId = $referencedCashPointClosingTransactionId;
    }

    public function getReferencedCashPointClosingTransaction(): ?CashPointClosingTransactionEntity
    {
        if (!$this->referencedCashPointClosingTransaction && $this->referencedCashPointClosingTransactionId) {
            throw new AssociationNotLoadedException('referencedCashPointClosingTransaction', $this);
        }

        return $this->referencedCashPointClosingTransaction;
    }

    public function setReferencedCashPointClosingTransaction(
        ?CashPointClosingTransactionEntity $referencedCashPointClosingTransaction,
    ): void {
        $this->referencedCashPointClosingTransaction = $referencedCashPointClosingTransaction;
        $this->referencedCashPointClosingTransactionId = $referencedCashPointClosingTransaction ? $referencedCashPointClosingTransaction->getId() : null;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        if (
            !$productId
            || ($this->product && $this->product->getId() !== $productId)
        ) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        if (
            !$productVersionId
            || ($this->product && $this->product->getVersionId() !== $productVersionId)
        ) {
            $this->product = null;
        }
        $this->productVersionId = $productVersionId;
    }

    public function getProduct(): ?ProductEntity
    {
        if (!$this->product && $this->productId) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
        $this->productId = $product ? $product->getId() : null;
        $this->productVersionId = $product ? $product->getVersionId() : null;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function setProductNumber(string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): void
    {
        $this->gtin = $gtin;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getVoucherId(): ?string
    {
        return $this->voucherId;
    }

    public function setVoucherId(?string $voucherId): void
    {
        $this->voucherId = $voucherId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getVatTable(): array
    {
        return $this->vatTable;
    }

    public function setVatTable(array $vatTable): void
    {
        $this->vatTable = $vatTable;
    }

    public function getPricePerUnit(): array
    {
        return $this->pricePerUnit;
    }

    public function setPricePerUnit(array $pricePerUnit): void
    {
        $this->pricePerUnit = $pricePerUnit;
    }

    public function getTotal(): array
    {
        return $this->total;
    }

    public function setTotal(array $total): void
    {
        $this->total = $total;
    }

    public function getDiscount(): ?array
    {
        return $this->discount;
    }

    public function setDiscount(?array $discount): void
    {
        $this->discount = $discount;
    }
}
