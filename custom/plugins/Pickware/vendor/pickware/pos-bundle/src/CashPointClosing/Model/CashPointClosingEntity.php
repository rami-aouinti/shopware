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
use Pickware\PickwarePos\CashPointClosing\CustomAggregation\CashPointClosingCustomAggregation;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class CashPointClosingEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $cashRegisterFiskalyClientUuid;
    protected int $number;
    protected DateTimeInterface $exportCreationDate;
    protected array $cashStatement;
    protected string $cashRegisterId;
    protected ?CashRegisterEntity $cashRegister = null;
    protected ?string $userId;
    protected ?UserEntity $user = null;
    protected array $userSnapshot;
    protected ?CashPointClosingTransactionCollection $cashPointClosingTransactions = null;
    protected CashPointClosingCustomAggregation $customAggregation;

    public function getCashRegisterFiskalyClientUuid(): ?string
    {
        return $this->cashRegisterFiskalyClientUuid;
    }

    public function setCashRegisterFiskalyClientUuid(?string $cashRegisterFiskalyClientUuid): void
    {
        $this->cashRegisterFiskalyClientUuid = $cashRegisterFiskalyClientUuid;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getExportCreationDate(): DateTimeInterface
    {
        return $this->exportCreationDate;
    }

    public function setExportCreationDate(DateTimeInterface $exportCreationDate): void
    {
        $this->exportCreationDate = $exportCreationDate;
    }

    public function getCashPointClosingTransactions(): CashPointClosingTransactionCollection
    {
        if (!$this->cashPointClosingTransactions) {
            throw new AssociationNotLoadedException('cashPointClosingTransactions', $this);
        }

        return $this->cashPointClosingTransactions;
    }

    public function setCashPointClosingTransactions(
        CashPointClosingTransactionCollection $cashPointClosingTransactions,
    ): void {
        $this->cashPointClosingTransactions = $cashPointClosingTransactions;
    }

    public function getCashStatement(): array
    {
        return $this->cashStatement;
    }

    public function setCashStatement(array $cashStatement): void
    {
        $this->cashStatement = $cashStatement;
    }

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

    public function getCustomAggregation(): CashPointClosingCustomAggregation
    {
        return $this->customAggregation;
    }

    public function setCustomAggregation(CashPointClosingCustomAggregation $customAggregation): void
    {
        $this->customAggregation = $customAggregation;
    }
}
