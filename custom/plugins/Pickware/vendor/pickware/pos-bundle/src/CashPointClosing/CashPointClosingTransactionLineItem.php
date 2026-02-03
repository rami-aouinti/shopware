<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing;

use JsonSerializable;

class CashPointClosingTransactionLineItem implements JsonSerializable
{
    private string $id;
    private ?string $productId;
    private string $name;
    private string $productNumber;
    private ?string $gtin;
    private ?string $voucherId;
    private string $type;
    private int $quantity;
    private array $vatTable;
    private Price $pricePerUnit;
    private Price $total;
    private ?Price $discount;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'name' => $this->name,
            'productNumber' => $this->productNumber,
            'gtin' => $this->gtin,
            'voucherId' => $this->voucherId,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'vatTable' => $this->vatTable,
            'pricePerUnit' => $this->pricePerUnit,
            'total' => $this->total,
            'discount' => $this->discount,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->id = $array['id'];
        $self->productId = $array['productId'];
        $self->name = $array['name'];
        $self->productNumber = $array['productNumber'];
        $self->gtin = $array['gtin'];
        $self->voucherId = $array['voucherId'];
        $self->type = $array['type'];
        $self->quantity = $array['quantity'];
        $self->vatTable = $array['vatTable'];
        $self->pricePerUnit = Price::fromArray($array['pricePerUnit']);
        $self->total = Price::fromArray($array['total']);
        if (isset($array['discount'])) {
            $self->discount = Price::fromArray($array['discount']);
        } else {
            $self->discount = null;
        }

        return $self;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getVoucherId(): ?string
    {
        return $this->voucherId;
    }

    public function setVoucherId(?string $voucherId): void
    {
        $this->voucherId = $voucherId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
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

    public function getPricePerUnit(): Price
    {
        return $this->pricePerUnit;
    }

    public function setPricePerUnit(Price $pricePerUnit): void
    {
        $this->pricePerUnit = $pricePerUnit;
    }

    public function getTotal(): Price
    {
        return $this->total;
    }

    public function setTotal(Price $total): void
    {
        $this->total = $total;
    }

    public function getDiscount(): ?Price
    {
        return $this->discount;
    }

    public function setDiscount(?Price $discount): void
    {
        $this->discount = $discount;
    }
}
