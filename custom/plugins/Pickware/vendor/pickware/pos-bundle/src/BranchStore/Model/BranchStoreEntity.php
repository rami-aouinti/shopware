<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwarePos\Address\Model\AddressEntity;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class BranchStoreEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;
    protected ?string $fiskalyOrganizationUuid;
    protected ?string $salesChannelId;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?string $addressId;
    protected ?AddressEntity $address = null;
    protected ?CashRegisterCollection $cashRegisters = null;
    protected ?OrderCollection $orders = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFiskalyOrganizationUuid(): ?string
    {
        return $this->fiskalyOrganizationUuid;
    }

    public function setFiskalyOrganizationUuid(?string $fiskalyOrganizationUuid): void
    {
        $this->fiskalyOrganizationUuid = $fiskalyOrganizationUuid;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        if ($salesChannelId && $this->salesChannel && $this->salesChannel->getId() !== $salesChannelId) {
            $this->salesChannel = null;
        }
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        if (!$this->salesChannel && $this->salesChannelId !== null) {
            throw new AssociationNotLoadedException('salesChannel', $this);
        }

        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        if ($salesChannel) {
            $this->salesChannelId = $salesChannel->getId();
        }
        $this->salesChannel = $salesChannel;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): void
    {
        if ($this->address && $this->address->getId() !== $addressId) {
            $this->address = null;
        }
        $this->addressId = $addressId;
    }

    public function getAddress(): ?AddressEntity
    {
        if (!$this->address && $this->addressId !== null) {
            throw new AssociationNotLoadedException('address', $this);
        }

        return $this->address;
    }

    public function setAddress(?AddressEntity $address): void
    {
        if ($address) {
            $this->addressId = $address->getId();
        }
        $this->address = $address;
    }

    public function getCashRegisters(): CashRegisterCollection
    {
        if (!$this->cashRegisters) {
            throw new AssociationNotLoadedException('cashRegisters', $this);
        }

        return $this->cashRegisters;
    }

    public function setCashRegisters(CashRegisterCollection $cashRegisters): void
    {
        $this->cashRegisters = $cashRegisters;
    }

    public function getOrders(): OrderCollection
    {
        if (!$this->orders) {
            throw new AssociationNotLoadedException('orders', $this);
        }

        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }
}
