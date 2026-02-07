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
use Pickware\DalBundle\EnumSupportingCloneTrait;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ReportEntity extends Entity
{
    use EntityIdTrait;
    use EnumSupportingCloneTrait;

    /**
     * "Stichtag", day of the report
     */
    protected DateTimeInterface $reportingDay;

    /**
     * Depending on the time zone the reporting day will result in a different "untilDate".
     *
     * Example: Reporting day: 31.12.2015, time zone: europe/berlin => until date: 31.12.2015 23:00:00 UTC
     */
    protected string $reportingDayTimeZone;

    /**
     * All stock entries prior to this point in time will be used for the report.
     */
    protected ?DateTimeInterface $untilDate;

    /**
     * If a report has generated = false it means it was not generated completely or not at all. (Maybe because of an
     * error during the generation.)
     */
    protected bool $generated;

    protected ReportGenerationStep $generationStep;
    protected ?string $comment;
    protected bool $preview;
    protected ReportMethod $method;
    protected ?ReportRowCollection $rows = null;
    protected ?string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected array $warehouseSnapshot;

    public function getReportingDay(): DateTimeInterface
    {
        return $this->reportingDay;
    }

    public function setReportingDay(DateTimeInterface $reportingDay): void
    {
        $this->reportingDay = $reportingDay;
    }

    public function getReportingDayTimeZone(): string
    {
        return $this->reportingDayTimeZone;
    }

    public function setReportingDayTimeZone(string $reportingDayTimeZone): void
    {
        $this->reportingDayTimeZone = $reportingDayTimeZone;
    }

    public function getUntilDate(): ?DateTimeInterface
    {
        return $this->untilDate;
    }

    public function setUntilDate(?DateTimeInterface $untilDate): void
    {
        $this->untilDate = $untilDate;
    }

    public function isGenerated(): bool
    {
        return $this->generated;
    }

    public function setGenerated(bool $generated): void
    {
        $this->generated = $generated;
    }

    public function getGenerationStep(): ReportGenerationStep
    {
        return $this->generationStep;
    }

    public function setGenerationStep(ReportGenerationStep $generationStep): void
    {
        $this->generationStep = $generationStep;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    public function setPreview(bool $preview): void
    {
        $this->preview = $preview;
    }

    public function getMethod(): ReportMethod
    {
        return $this->method;
    }

    public function setMethod(ReportMethod $method): void
    {
        $this->method = $method;
    }

    public function getRows(): ReportRowCollection
    {
        if (!$this->rows) {
            throw new AssociationNotLoadedException('rows', $this);
        }

        return $this->rows;
    }

    public function setRows(?ReportRowCollection $rows): void
    {
        $this->rows = $rows;
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
        if (!$this->warehouse && $this->warehouseId) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(?WarehouseEntity $warehouse): void
    {
        if ($warehouse) {
            $this->warehouseId = $warehouse->getId();
        }
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
}
