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

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ReportRowEntity extends Entity
{
    use EntityIdTrait;

    protected string $reportId;
    protected ?ReportEntity $report;
    protected ?string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected array $productSnapshot;
    protected int $stock;
    protected ?float $valuationNet;
    protected ?float $valuationGross;
    protected float $taxRate;
    protected float $averagePurchasePriceNet;
    protected ?PurchaseCollection $purchases = null;
    protected ?PurchaseEntity $carryOverPurchase = null;

    /**
     * If there is more stock than the sum of purchased stock, this is the surplus stock, that is not
     * included in purchases
     */
    protected int $surplusStock;

    /**
     * The surplus stock will be valued by a "guessed" purchase price. This purchase price is saved for
     * documentation.
     */
    protected ?float $surplusPurchasePriceNet;

    public function getReportId(): string
    {
        return $this->reportId;
    }

    public function setReportId(string $reportId): void
    {
        if ($this->report && $this->report->getId() !== $reportId) {
            $this->report = null;
        }
        $this->reportId = $reportId;
    }

    public function getReport(): ReportEntity
    {
        if (!$this->report) {
            throw new AssociationNotLoadedException('report', $this);
        }

        return $this->report;
    }

    public function setReport(ReportEntity $report): void
    {
        $this->report = $report;
        $this->reportId = $report->getId();
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        if ($this->product && $this->product->getId() !== $productId) {
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
        if ($this->product && $this->product->getVersionId() !== $productVersionId) {
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
        if ($product) {
            $this->productId = $product->getId();
        }
        $this->product = $product;
    }

    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    public function setProductSnapshot(array $productSnapshot): void
    {
        $this->productSnapshot = $productSnapshot;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getValuationNet(): ?float
    {
        return $this->valuationNet;
    }

    public function setValuationNet(?float $valuationNet): void
    {
        $this->valuationNet = $valuationNet;
    }

    public function getValuationGross(): ?float
    {
        return $this->valuationGross;
    }

    public function setValuationGross(?float $valuationGross): void
    {
        $this->valuationGross = $valuationGross;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getAveragePurchasePriceNet(): float
    {
        return $this->averagePurchasePriceNet;
    }

    public function setAveragePurchasePriceNet(float $averagePurchasePriceNet): void
    {
        $this->averagePurchasePriceNet = $averagePurchasePriceNet;
    }

    public function getPurchases(): PurchaseCollection
    {
        if (!$this->purchases) {
            throw new AssociationNotLoadedException('purchases', $this);
        }

        return $this->purchases;
    }

    public function setPurchases(?PurchaseCollection $purchases): void
    {
        $this->purchases = $purchases;
    }

    public function getSurplusStock(): int
    {
        return $this->surplusStock;
    }

    public function setSurplusStock(int $surplusStock): void
    {
        $this->surplusStock = $surplusStock;
    }

    public function getSurplusPurchasePriceNet(): ?float
    {
        return $this->surplusPurchasePriceNet;
    }

    public function setSurplusPurchasePriceNet(?float $surplusPurchasePriceNet): void
    {
        $this->surplusPurchasePriceNet = $surplusPurchasePriceNet;
    }

    public function getCarryOverPurchase(): ?PurchaseEntity
    {
        return $this->carryOverPurchase;
    }

    public function setCarryOverPurchase(?PurchaseEntity $carryOverPurchase): void
    {
        $this->carryOverPurchase = $carryOverPurchase;
    }
}
