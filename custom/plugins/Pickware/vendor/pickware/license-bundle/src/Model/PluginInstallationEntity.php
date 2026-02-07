<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Model;

use DateTimeInterface;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicense;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicenseLease;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PluginInstallationEntity extends Entity
{
    use EntityIdTrait;

    protected string $installationId;
    protected string $installationUuid;
    protected ?string $pickwareAccountAccessToken;
    protected ?PickwareLicense $pickwareLicense;
    protected ?PickwareLicenseLease $pickwareLicenseLease;
    protected ?JsonApiError $latestPickwareLicenseLeaseRefreshError;
    protected ?JsonApiError $latestPickwareLicenseRefreshError;
    protected ?JsonApiError $latestUsageReportError;
    protected ?DateTimeInterface $lastPickwareLicenseLeaseRefreshedAt;

    public function getInstallationId(): string
    {
        return $this->installationId;
    }

    public function setInstallationId(string $installationId): void
    {
        $this->installationId = $installationId;
    }

    public function getInstallationUuid(): string
    {
        return $this->installationUuid;
    }

    public function setInstallationUuid(string $installationUuid): void
    {
        $this->installationUuid = $installationUuid;
    }

    public function getPickwareLicense(): ?PickwareLicense
    {
        return $this->pickwareLicense;
    }

    public function setPickwareLicense(?PickwareLicense $pickwareLicense): void
    {
        $this->pickwareLicense = $pickwareLicense;
    }

    public function getPickwareAccountAccessToken(): ?string
    {
        return $this->pickwareAccountAccessToken;
    }

    public function setPickwareAccountAccessToken(?string $pickwareAccountAccessToken): void
    {
        $this->pickwareAccountAccessToken = $pickwareAccountAccessToken;
    }

    public function getPickwareLicenseLease(): ?PickwareLicenseLease
    {
        return $this->pickwareLicenseLease;
    }

    public function setPickwareLicenseLease(?PickwareLicenseLease $pickwareLicenseLease): void
    {
        $this->pickwareLicenseLease = $pickwareLicenseLease;
    }

    public function getLatestPickwareLicenseLeaseRefreshError(): ?JsonApiError
    {
        return $this->latestPickwareLicenseLeaseRefreshError;
    }

    public function setLatestPickwareLicenseLeaseRefreshError(?JsonApiError $latestPickwareLicenseLeaseRefreshError): void
    {
        $this->latestPickwareLicenseLeaseRefreshError = $latestPickwareLicenseLeaseRefreshError;
    }

    public function getLatestPickwareLicenseRefreshError(): ?JsonApiError
    {
        return $this->latestPickwareLicenseRefreshError;
    }

    public function setLatestPickwareLicenseRefreshError(?JsonApiError $latestPickwareLicenseRefreshError): void
    {
        $this->latestPickwareLicenseRefreshError = $latestPickwareLicenseRefreshError;
    }

    public function getLatestUsageReportError(): ?JsonApiError
    {
        return $this->latestUsageReportError;
    }

    public function setLatestUsageReportError(?JsonApiError $latestUsageReportError): void
    {
        $this->latestUsageReportError = $latestUsageReportError;
    }

    public function getLastPickwareLicenseLeaseRefreshedAt(): ?DateTimeInterface
    {
        return $this->lastPickwareLicenseLeaseRefreshedAt;
    }

    public function setLastPickwareLicenseLeaseRefreshedAt(?DateTimeInterface $lastPickwareLicenseLeaseRefreshedAt): void
    {
        $this->lastPickwareLicenseLeaseRefreshedAt = $lastPickwareLicenseLeaseRefreshedAt;
    }
}
