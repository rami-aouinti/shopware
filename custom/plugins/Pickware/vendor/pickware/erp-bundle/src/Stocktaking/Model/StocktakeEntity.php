<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Stocktaking\ProductSummary\Model\StocktakeProductSummaryCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StocktakeEntity extends Entity
{
    use EntityIdTrait;

    protected string $title;
    protected string $number;
    protected ?DateTimeInterface $completedAt;
    protected bool $isActive;
    protected string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected array $warehouseSnapshot;
    protected ?string $importExportId;
    protected ?ImportExportEntity $importExport = null;
    protected ?StocktakeCountingProcessCollection $countingProcesses = null;
    protected ?StocktakeProductSummaryCollection $stocktakeProductSummaries = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(string $warehouseId): void
    {
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseId) {
            $this->warehouse = null;
        }
        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): WarehouseEntity
    {
        if (!$this->warehouse) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(WarehouseEntity $warehouse): void
    {
        $this->warehouse = $warehouse;
        $this->warehouseId = $warehouse->getId();
    }

    public function getWarehouseSnapshot(): array
    {
        return $this->warehouseSnapshot;
    }

    public function setWarehouseSnapshot(array $warehouseSnapshot): void
    {
        $this->warehouseSnapshot = $warehouseSnapshot;
    }

    public function getImportExportId(): ?string
    {
        return $this->importExportId;
    }

    public function setImportExportId(?string $importExportId): void
    {
        if ($this->importExport && $this->importExport->getId() !== $importExportId) {
            $this->importExport = null;
        }
        $this->importExportId = $importExportId;
    }

    public function getImportExport(): ?ImportExportEntity
    {
        if (!$this->importExport && $this->importExportId) {
            throw new AssociationNotLoadedException('importExport', $this);
        }

        return $this->importExport;
    }

    public function setImportExport(?ImportExportEntity $importExport): void
    {
        if ($importExport) {
            $this->importExportId = $importExport->getId();
        }
        $this->importExport = $importExport;
    }

    public function getCountingProcesses(): StocktakeCountingProcessCollection
    {
        if (!$this->countingProcesses) {
            throw new AssociationNotLoadedException('countingProcesses', $this);
        }

        return $this->countingProcesses;
    }

    public function setCountingProcesses(StocktakeCountingProcessCollection $countingProcesses): void
    {
        $this->countingProcesses = $countingProcesses;
    }

    public function getStocktakeProductSummaries(): StocktakeProductSummaryCollection
    {
        if (!$this->stocktakeProductSummaries) {
            throw new AssociationNotLoadedException('stocktakeProductSummaries', $this);
        }

        return $this->stocktakeProductSummaries;
    }

    public function setStocktakeProductSummaries(StocktakeProductSummaryCollection $stocktakeProductSummaries): void
    {
        $this->stocktakeProductSummaries = $stocktakeProductSummaries;
    }

    public function getCompletedAt(): ?DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeInterface $completedAt): void
    {
        $this->completedAt = $completedAt;
    }
}
