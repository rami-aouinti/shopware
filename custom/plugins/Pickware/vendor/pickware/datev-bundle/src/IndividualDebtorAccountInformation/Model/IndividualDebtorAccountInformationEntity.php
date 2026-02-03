<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class IndividualDebtorAccountInformationEntity extends Entity
{
    use EntityIdTrait;

    protected int $account;
    protected string $customerId;
    protected ?CustomerEntity $customer = null;
    protected string $importExportId;
    protected ?ImportExportEntity $importExport;

    public function getAccount(): int
    {
        return $this->account;
    }

    public function setAccount(int $account): void
    {
        $this->account = $account;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        if ($this->customer && $this->customer->getId() !== $customerId) {
            $this->customer = null;
        }

        $this->customerId = $customerId;
    }

    public function getCustomer(): CustomerEntity
    {
        if (!$this->customer) {
            throw new AssociationNotLoadedException('customer', $this);
        }

        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customerId = $customer->getId();
        $this->customer = $customer;
    }

    public function getImportExportId(): string
    {
        return $this->importExportId;
    }

    public function setImportExportId(string $importExportId): void
    {
        if ($this->importExport && $this->importExport->getId() !== $importExportId) {
            $this->importExport = null;
        }

        $this->importExportId = $importExportId;
    }

    public function getImportExport(): ImportExportEntity
    {
        if (!$this->importExport) {
            throw new AssociationNotLoadedException('importExport', $this);
        }

        return $this->importExport;
    }

    public function setImportExports(ImportExportEntity $importExport): void
    {
        $this->importExport = $importExport;
    }
}
