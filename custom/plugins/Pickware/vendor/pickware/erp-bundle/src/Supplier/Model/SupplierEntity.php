<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Address\Model\AddressEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptCollection;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Language\LanguageEntity;

class SupplierEntity extends Entity
{
    use EntityIdTrait;

    protected string $number;
    protected string $name;
    protected ?string $customerNumber = null;
    protected ?int $defaultDeliveryTime = null;
    protected string $languageId;
    protected ?LanguageEntity $language = null;
    protected ?string $addressId = null;
    protected ?AddressEntity $address = null;
    protected ?array $customFields = null;
    protected ?ProductSupplierConfigurationCollection $productSupplierConfigurations = null;
    protected ?GoodsReceiptCollection $goodsReceipts = null;
    protected ?SupplierOrderCollection $orders = null;

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(?string $customerNumber): void
    {
        $this->customerNumber = $customerNumber;
    }

    public function getDefaultDeliveryTime(): ?int
    {
        return $this->defaultDeliveryTime;
    }

    public function setDefaultDeliveryTime(?int $defaultDeliveryTime): void
    {
        $this->defaultDeliveryTime = $defaultDeliveryTime;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        if ($this->language && $this->language->getId() !== $languageId) {
            $this->language = null;
        }
        $this->languageId = $languageId;
    }

    public function getLanguage(): LanguageEntity
    {
        if (!$this->language) {
            throw new AssociationNotLoadedException('language', $this);
        }

        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        if ($language) {
            $this->languageId = $language->getId();
        }
        $this->language = $language;
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

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getProductSupplierConfigurations(): ?ProductSupplierConfigurationCollection
    {
        if (!$this->productSupplierConfigurations) {
            throw new AssociationNotLoadedException('productSupplierConfigurations', $this);
        }

        return $this->productSupplierConfigurations;
    }

    public function setProductSupplierConfigurations(
        ?ProductSupplierConfigurationCollection $productSupplierConfigurations,
    ): void {
        $this->productSupplierConfigurations = $productSupplierConfigurations;
    }

    public function getGoodsReceipts(): GoodsReceiptCollection
    {
        if (!$this->goodsReceipts) {
            throw new AssociationNotLoadedException('goodsReceipts', $this);
        }

        return $this->goodsReceipts;
    }

    public function setGoodsReceipts(?GoodsReceiptCollection $goodsReceipts): void
    {
        $this->goodsReceipts = $goodsReceipts;
    }

    public function getOrders(): SupplierOrderCollection
    {
        if (!$this->orders) {
            throw new AssociationNotLoadedException('orders', $this);
        }

        return $this->orders;
    }

    public function setOrders(?SupplierOrderCollection $orders): void
    {
        $this->orders = $orders;
    }
}
