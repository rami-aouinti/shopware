<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class AnalyticsAggregationSessionEntity extends Entity
{
    use EntityIdTrait;

    protected string $aggregationTechnicalName;
    protected ?AnalyticsAggregationEntity $aggregation = null;
    protected array $config;
    protected string $userId;
    protected ?UserEntity $user = null;
    protected ?DateTimeInterface $lastCalculation;
    protected ?AnalyticsReportConfigCollection $reportConfigs = null;

    public function getAggregationTechnicalName(): string
    {
        return $this->aggregationTechnicalName;
    }

    public function setAggregationTechnicalName(string $aggregationTechnicalName): void
    {
        if ($this->aggregation && $this->aggregation->getTechnicalName() !== $aggregationTechnicalName) {
            $this->aggregation = null;
        }

        $this->aggregationTechnicalName = $aggregationTechnicalName;
    }

    public function getAggregation(): AnalyticsAggregationEntity
    {
        if (!$this->aggregation) {
            throw new AssociationNotLoadedException('aggregation', $this);
        }

        return $this->aggregation;
    }

    public function setAggregation(AnalyticsAggregationEntity $aggregation): void
    {
        $this->aggregation = $aggregation;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }

        $this->userId = $userId;
    }

    public function getUser(): UserEntity
    {
        if (!$this->user) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getLastCalculation(): ?DateTimeInterface
    {
        return $this->lastCalculation;
    }

    public function setLastCalculation(?DateTimeInterface $lastCalculation): void
    {
        $this->lastCalculation = $lastCalculation;
    }

    public function getReportConfigs(): AnalyticsReportConfigCollection
    {
        if (!$this->reportConfigs) {
            throw new AssociationNotLoadedException('reportConfigs', $this);
        }

        return $this->reportConfigs;
    }

    public function setReportConfigs(AnalyticsReportConfigCollection $reportConfigs): void
    {
        $this->reportConfigs = $reportConfigs;
    }
}
