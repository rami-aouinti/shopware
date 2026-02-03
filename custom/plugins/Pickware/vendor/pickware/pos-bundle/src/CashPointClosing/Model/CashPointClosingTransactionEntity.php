<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\User\UserEntity;

class CashPointClosingTransactionEntity extends Entity
{
    use EntityIdTrait;

    protected string $cashRegisterId;
    protected ?CashRegisterEntity $cashRegister = null;
    protected ?string $cashPointClosingId;
    protected ?CashPointClosingEntity $cashPointClosing = null;
    protected string $currencyId;
    protected ?CurrencyEntity $currency = null;
    protected int $number;
    protected string $type;
    protected ?string $name;
    protected DateTimeInterface $start;
    protected DateTimeInterface $end;
    protected ?string $userId;
    protected ?UserEntity $user = null;
    protected ?string $customerId;
    protected ?CustomerEntity $customer = null;
    protected array $userSnapshot;
    protected array $buyer;
    protected array $total;
    protected array $payment;
    protected ?string $comment;
    protected ?array $vatTable;
    protected ?CashPointClosingTransactionLineItemCollection $cashPointClosingTransactionLineItems = null;
    protected ?array $fiscalizationContext;

    public function getCashRegisterId(): string
    {
        return $this->cashRegisterId;
    }

    public function setCashRegisterId(string $cashRegisterId): void
    {
        if ($this->cashRegister && $this->cashRegister->getId() !== $cashRegisterId) {
            $this->cashRegister = null;
        }
        $this->cashRegisterId = $cashRegisterId;
    }

    public function getCashRegister(): CashRegisterEntity
    {
        if (!$this->cashRegister) {
            throw new AssociationNotLoadedException('cashRegister', $this);
        }

        return $this->cashRegister;
    }

    public function setCashRegister(CashRegisterEntity $cashRegister): void
    {
        $this->cashRegister = $cashRegister;
        $this->cashRegisterId = $cashRegister->getId();
    }

    public function getCashPointClosingId(): ?string
    {
        return $this->cashPointClosingId;
    }

    public function setCashPointClosingId(?string $cashPointClosingId): void
    {
        if ($this->cashPointClosing && $this->cashPointClosing->getId() !== $cashPointClosingId) {
            $this->cashPointClosing = null;
        }
        $this->cashPointClosingId = $cashPointClosingId;
    }

    public function getCashPointClosing(): ?CashPointClosingEntity
    {
        if (!$this->cashPointClosing && $this->cashPointClosingId) {
            throw new AssociationNotLoadedException('cashPointClosing', $this);
        }

        return $this->cashPointClosing;
    }

    public function setCashPointClosing(?CashPointClosingEntity $cashPointClosing): void
    {
        $this->cashPointClosing = $cashPointClosing;
        $this->cashPointClosingId = $cashPointClosing ? $cashPointClosing->getId() : null;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): CurrencyEntity
    {
        if (!$this->currency) {
            throw new AssociationNotLoadedException('currency', $this);
        }

        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
        $this->currencyId = $currency->getId();
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getStart(): DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(DateTimeInterface $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(DateTimeInterface $end): void
    {
        $this->end = $end;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if (!$userId || ($this->user && $this->user->getId() !== $userId)) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if ($this->userId && !$this->user) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
        $this->userId = $user ? $user->getId() : null;
    }

    public function getUserSnapshot(): array
    {
        return $this->userSnapshot;
    }

    public function setUserSnapshot(array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        if (!$customerId || ($this->customer && $this->customer->getId() !== $customerId)) {
            $this->customer = null;
        }
        $this->customerId = $customerId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        if ($this->customerId && !$this->customer) {
            throw new AssociationNotLoadedException('customer', $this);
        }

        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
        $this->customerId = $customer ? $customer->getId() : null;
    }

    public function getBuyer(): array
    {
        return $this->buyer;
    }

    public function setBuyer(array $buyer): void
    {
        $this->buyer = $buyer;
    }

    public function getTotal(): array
    {
        return $this->total;
    }

    public function setTotal(array $total): void
    {
        $this->total = $total;
    }

    public function getPayment(): array
    {
        return $this->payment;
    }

    public function setPayment(array $payment): void
    {
        $this->payment = $payment;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getVatTable(): ?array
    {
        return $this->vatTable;
    }

    public function setVatTable(?array $vatTable): void
    {
        $this->vatTable = $vatTable;
    }

    public function getCashPointClosingTransactionLineItems(): CashPointClosingTransactionLineItemCollection
    {
        if (!$this->cashPointClosingTransactionLineItems) {
            throw new AssociationNotLoadedException('cashPointClosingTransactionLineItems', $this);
        }

        return $this->cashPointClosingTransactionLineItems;
    }

    public function setCashPointClosingTransactionLineItems(
        CashPointClosingTransactionLineItemCollection $cashPointClosingTransactionLineItems,
    ): void {
        $this->cashPointClosingTransactionLineItems = $cashPointClosingTransactionLineItems;
    }

    public function getFiscalizationContext(): ?array
    {
        return $this->fiscalizationContext;
    }

    public function setFiscalizationContext(?array $fiscalizationContext): void
    {
        $this->fiscalizationContext = $fiscalizationContext;
    }
}
