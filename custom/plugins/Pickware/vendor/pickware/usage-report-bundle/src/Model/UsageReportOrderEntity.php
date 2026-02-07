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
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class UsageReportOrderEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;
    protected ?string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected string $orderType;
    protected ?string $usageReportId = null;
    protected ?UsageReportEntity $usageReport = null;
    protected DateTimeInterface $orderedAt;
    protected DateTimeInterface $orderCreatedAt;
    protected DateTimeInterface $orderCreatedAtHour;
    protected ?array $orderSnapshot = null;

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(?string $orderVersionId): void
    {
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): ?OrderEntity
    {
        if ($this->orderId && !$this->order) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->orderId = $order?->getId();
        $this->orderVersionId = $order?->getVersionId();
        $this->order = $order;
    }

    public function getOrderType(): string
    {
        return $this->orderType;
    }

    public function setOrderType(string $orderType): void
    {
        $this->orderType = $orderType;
    }

    public function getUsageReportId(): ?string
    {
        return $this->usageReportId;
    }

    public function setUsageReportId(?string $usageReportId): void
    {
        if ($this->usageReport && $this->usageReport->getId() !== $usageReportId) {
            $this->usageReport = null;
        }
        $this->usageReportId = $usageReportId;
    }

    public function getUsageReport(): ?UsageReportEntity
    {
        if ($this->usageReportId && !$this->usageReport) {
            throw new AssociationNotLoadedException('usageReport', $this);
        }

        return $this->usageReport;
    }

    public function setUsageReport(?UsageReportEntity $usageReport): void
    {
        $this->usageReportId = $usageReport?->getId();
        $this->usageReport = $usageReport;
    }

    public function getOrderedAt(): DateTimeInterface
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(DateTimeInterface $orderedAt): void
    {
        $this->orderedAt = $orderedAt;
    }

    public function getOrderCreatedAt(): DateTimeInterface
    {
        return $this->orderCreatedAt;
    }

    public function setOrderCreatedAt(DateTimeInterface $orderCreatedAt): void
    {
        $this->orderCreatedAt = $orderCreatedAt;
    }

    public function getOrderCreatedAtHour(): DateTimeInterface
    {
        return $this->orderCreatedAtHour;
    }

    public function setOrderCreatedAtHour(DateTimeInterface $orderCreatedAtHour): void
    {
        $this->orderCreatedAtHour = $orderCreatedAtHour;
    }

    public function getOrderSnapshot(): ?array
    {
        return $this->orderSnapshot;
    }

    public function setOrderSnapshot(?array $orderSnapshot): void
    {
        $this->orderSnapshot = $orderSnapshot;
    }
}
