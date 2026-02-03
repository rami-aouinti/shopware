<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PurchaseEntity extends Entity
{
    use EntityIdTrait;

    protected ?ReportRowEntity $reportRow = null;
    protected string $reportRowId;
    protected DateTimeInterface $date;
    protected float $purchasePriceNet;
    protected int $quantity;
    protected int $quantityUsedForValuation;
    protected PurchaseType $type;
    protected ?GoodsReceiptLineItemEntity $goodsReceiptLineItem = null;
    protected ?string $goodsReceiptLineItemId;
    protected ?ReportRowEntity $carryOverReportRow = null;
    protected ?string $carryOverReportRowId;

    public function getReportRowId(): string
    {
        return $this->reportRowId;
    }

    public function setReportRowId(string $reportRowId): void
    {
        if ($this->reportRow && $this->reportRow->getId() !== $reportRowId) {
            $this->reportRow = null;
        }
        $this->reportRowId = $reportRowId;
    }

    public function getReportRow(): ?ReportRowEntity
    {
        if (!$this->reportRow && $this->reportRowId) {
            throw new AssociationNotLoadedException('reportRow', $this);
        }

        return $this->reportRow;
    }

    public function setReportRow(?ReportRowEntity $reportRow): void
    {
        if ($reportRow) {
            $this->reportRowId = $reportRow->getId();
        }
        $this->reportRow = $reportRow;
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getPurchasePriceNet(): float
    {
        return $this->purchasePriceNet;
    }

    public function setPurchasePriceNet(float $purchasePriceNet): void
    {
        $this->purchasePriceNet = $purchasePriceNet;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getQuantityUsedForValuation(): int
    {
        return $this->quantityUsedForValuation;
    }

    public function setQuantityUsedForValuation(int $quantityUsedForValuation): void
    {
        $this->quantityUsedForValuation = $quantityUsedForValuation;
    }

    public function getType(): PurchaseType
    {
        return $this->type;
    }

    public function setType(PurchaseType $type): void
    {
        $this->type = $type;
    }

    public function getGoodsReceiptLineItemId(): ?string
    {
        return $this->goodsReceiptLineItemId;
    }

    public function setGoodsReceiptLineItemId(?string $goodsReceiptLineItemId): void
    {
        if ($this->goodsReceiptLineItem && $this->goodsReceiptLineItem->getId() !== $goodsReceiptLineItemId) {
            $this->goodsReceiptLineItem = null;
        }
        $this->goodsReceiptLineItemId = $goodsReceiptLineItemId;
    }

    public function getGoodsReceiptLineItem(): ?GoodsReceiptLineItemEntity
    {
        if (!$this->goodsReceiptLineItem && $this->goodsReceiptLineItemId) {
            throw new AssociationNotLoadedException('goodsReceiptLineItem', $this);
        }

        return $this->goodsReceiptLineItem;
    }

    public function setGoodsReceiptLineItem(?GoodsReceiptLineItemEntity $goodsReceiptLineItem): void
    {
        if ($goodsReceiptLineItem) {
            $this->goodsReceiptLineItemId = $goodsReceiptLineItem->getId();
        }
        $this->goodsReceiptLineItem = $goodsReceiptLineItem;
    }

    public function getCarryOverReportRowId(): ?string
    {
        return $this->carryOverReportRowId;
    }

    public function setCarryOverReportRowId(?string $carryOverReportRowId): void
    {
        if ($this->carryOverReportRow && $this->carryOverReportRow->getId() !== $carryOverReportRowId) {
            $this->carryOverReportRow = null;
        }
        $this->carryOverReportRowId = $carryOverReportRowId;
    }

    public function getCarryOverReportRow(): ?ReportRowEntity
    {
        if (!$this->carryOverReportRow && $this->carryOverReportRowId) {
            throw new AssociationNotLoadedException('carryOverReportRow', $this);
        }

        return $this->carryOverReportRow;
    }

    public function setCarryOverReportRow(?ReportRowEntity $carryOverReportRow): void
    {
        if ($carryOverReportRow) {
            $this->carryOverReportRowId = $carryOverReportRow->getId();
        }
        $this->carryOverReportRow = $carryOverReportRow;
    }
}
