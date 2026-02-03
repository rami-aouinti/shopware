<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class UsageReportEntity extends Entity
{
    use EntityIdTrait;

    protected string $uuid;
    protected ?int $orderCount = null;
    protected ?DateTimeInterface $reportedAt;
    protected ?UsageReportOrderCollection $orders;
    protected DateTimeInterface $usageIntervalInclusiveStart;
    protected DateTimeInterface $usageIntervalExclusiveEnd;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getOrderCount(): ?int
    {
        return $this->orderCount;
    }

    public function setOrderCount(?int $orderCount): void
    {
        $this->orderCount = $orderCount;
    }

    public function getReportedAt(): ?DateTimeInterface
    {
        return $this->reportedAt;
    }

    public function setReportedAt(?DateTimeInterface $reportedAt): void
    {
        $this->reportedAt = $reportedAt;
    }

    public function getOrders(): UsageReportOrderCollection
    {
        if (!$this->orders) {
            throw new AssociationNotLoadedException('orders', $this);
        }

        return $this->orders;
    }

    public function setOrders(?UsageReportOrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getUsageIntervalInclusiveStart(): DateTimeInterface
    {
        return $this->usageIntervalInclusiveStart;
    }

    public function setUsageIntervalInclusiveStart(DateTimeInterface $usageIntervalInclusiveStart): void
    {
        $this->usageIntervalInclusiveStart = $usageIntervalInclusiveStart;
    }

    public function getUsageIntervalExclusiveEnd(): DateTimeInterface
    {
        return $this->usageIntervalExclusiveEnd;
    }

    public function setUsageIntervalExclusiveEnd(DateTimeInterface $usageIntervalExclusiveEnd): void
    {
        $this->usageIntervalExclusiveEnd = $usageIntervalExclusiveEnd;
    }
}
