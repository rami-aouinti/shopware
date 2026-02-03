<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionEntity;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsReportConfigEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DemandPlanningListItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $analyticsSessionId;
    protected ?AnalyticsAggregationSessionEntity $analyticsSession = null;
    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected int $sales;
    protected int $salesPrediction;
    protected int $reservedStock;
    protected int $availableStock;
    protected int $stock;
    protected ?int $reorderPoint;
    protected int $incomingStock;
    protected int $purchaseSuggestion;
    protected string $reportConfigId;
    protected ?AnalyticsReportConfigEntity $reportConfig = null;

    public function getAnalyticsSessionId(): string
    {
        return $this->analyticsSessionId;
    }

    public function setAnalyticsSessionId(string $analyticsSessionId): void
    {
        if ($this->analyticsSession && $this->analyticsSession->getId() !== $analyticsSessionId) {
            $this->analyticsSession = null;
        }

        $this->analyticsSessionId = $analyticsSessionId;
    }

    public function getAnalyticsSession(): AnalyticsAggregationSessionEntity
    {
        if (!$this->analyticsSession) {
            throw new AssociationNotLoadedException('analyticsSession', $this);
        }

        return $this->analyticsSession;
    }

    public function setAnalyticsSession(AnalyticsAggregationSessionEntity $analyticsSession): void
    {
        $this->analyticsSessionId = $analyticsSession->getId();
        $this->analyticsSession = $analyticsSession;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
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

    public function getProduct(): ProductEntity
    {
        if (!$this->product) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->productId = $product->getId();
        $this->productVersionId = $product->getVersionId();
        $this->product = $product;
    }

    public function getSales(): int
    {
        return $this->sales;
    }

    public function setSales(int $sales): void
    {
        $this->sales = $sales;
    }

    public function getSalesPrediction(): int
    {
        return $this->salesPrediction;
    }

    public function setSalesPrediction(int $salesPrediction): void
    {
        $this->salesPrediction = $salesPrediction;
    }

    public function getReservedStock(): int
    {
        return $this->reservedStock;
    }

    public function setReservedStock(int $reservedStock): void
    {
        $this->reservedStock = $reservedStock;
    }

    public function getAvailableStock(): int
    {
        return $this->availableStock;
    }

    public function setAvailableStock(int $availableStock): void
    {
        $this->availableStock = $availableStock;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getReorderPoint(): ?int
    {
        return $this->reorderPoint;
    }

    public function setReorderPoint(int $reorderPoint): void
    {
        $this->reorderPoint = $reorderPoint;
    }

    public function getIncomingStock(): int
    {
        return $this->incomingStock;
    }

    public function setIncomingStock(int $incomingStock): void
    {
        $this->incomingStock = $incomingStock;
    }

    public function getPurchaseSuggestion(): int
    {
        return $this->purchaseSuggestion;
    }

    public function setPurchaseSuggestion(int $purchaseSuggestion): void
    {
        $this->purchaseSuggestion = $purchaseSuggestion;
    }

    public function getReportConfigId(): string
    {
        return $this->reportConfigId;
    }

    public function setReportConfigId(string $reportConfigId): void
    {
        if ($this->reportConfig && $this->reportConfig->getId() !== $reportConfigId) {
            $this->reportConfig = null;
        }

        $this->reportConfigId = $reportConfigId;
    }

    public function getReportConfig(): ?AnalyticsReportConfigEntity
    {
        if (!$this->reportConfig) {
            throw new AssociationNotLoadedException('reportConfig', $this);
        }

        return $this->reportConfig;
    }

    public function setReportConfig(AnalyticsReportConfigEntity $reportConfig): void
    {
        $this->reportConfigId = $reportConfig->getId();
        $this->reportConfig = $reportConfig;
    }
}
