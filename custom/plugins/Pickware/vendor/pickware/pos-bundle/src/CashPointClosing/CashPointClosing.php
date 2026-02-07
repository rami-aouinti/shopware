<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing;

use DateTime;
use DateTimeInterface;
use JsonSerializable;
use Pickware\PickwarePos\CashPointClosing\CustomAggregation\CashPointClosingCustomAggregation;

/**
 * Struct class to parse and validate cash point closing data and moving it between services.
 */
class CashPointClosing implements JsonSerializable
{
    private ?string $cashRegisterFiskalyClientUuid;
    private ?int $number = null;
    private DateTimeInterface $exportCreationDate;
    private string $firstTransactionId;
    private string $lastTransactionId;

    /**
     * @var string[]
     */
    private array $transactionIds;

    private CashStatement $cashStatement;
    private string $userId;
    private array $userSnapShot;
    private string $cashRegisterId;
    private CashPointClosingCustomAggregation $customAggregation;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'cashRegisterFiskalyClientUuid' => $this->cashRegisterFiskalyClientUuid,
            'number' => $this->number,
            'exportCreationDate' => $this->exportCreationDate->format(DateTimeInterface::ATOM),
            'firstTransactionId' => $this->firstTransactionId,
            'lastTransactionId' => $this->lastTransactionId,
            'transactionIds' => $this->transactionIds,
            'cashStatement' => $this->cashStatement,
            'userId' => $this->userId,
            'userSnapshot' => $this->userSnapShot,
            'cashRegisterId' => $this->cashRegisterId,
            'customAggregation' => $this->customAggregation,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->cashRegisterFiskalyClientUuid = $array['cashRegisterFiskalyClientUuid'];
        $self->number = $array['number'];
        $self->exportCreationDate = DateTime::createFromFormat(DateTimeInterface::ATOM, $array['exportCreationDate']);
        $self->firstTransactionId = $array['firstTransactionId'];
        $self->lastTransactionId = $array['lastTransactionId'];
        $self->transactionIds = $array['transactionIds'];
        $self->cashStatement = CashStatement::fromArray($array['cashStatement']);
        $self->userId = $array['userId'];
        $self->userSnapShot = $array['userSnapshot'];
        $self->cashRegisterId = $array['cashRegisterId'];
        $self->customAggregation = CashPointClosingCustomAggregation::fromArray($array['customAggregation'] ?? []);

        return $self;
    }

    public function getCashRegisterFiskalyClientUuid(): ?string
    {
        return $this->cashRegisterFiskalyClientUuid;
    }

    public function setCashRegisterFiskalyClientUuid(?string $cashRegisterFiskalyClientUuid): void
    {
        $this->cashRegisterFiskalyClientUuid = $cashRegisterFiskalyClientUuid;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): void
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

    public function getFirstTransactionId(): string
    {
        return $this->firstTransactionId;
    }

    public function setFirstTransactionId(string $firstTransactionId): void
    {
        $this->firstTransactionId = $firstTransactionId;
    }

    public function getLastTransactionId(): string
    {
        return $this->lastTransactionId;
    }

    public function setLastTransactionId(string $lastTransactionId): void
    {
        $this->lastTransactionId = $lastTransactionId;
    }

    public function getTransactionIds(): array
    {
        return $this->transactionIds;
    }

    public function setTransactionIds(array $transactionIds): void
    {
        $this->transactionIds = $transactionIds;
    }

    public function getCashStatement(): CashStatement
    {
        return $this->cashStatement;
    }

    public function setCashStatement(CashStatement $cashStatement): void
    {
        $this->cashStatement = $cashStatement;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserSnapShot(): array
    {
        return $this->userSnapShot;
    }

    public function setUserSnapShot(array $userSnapShot): void
    {
        $this->userSnapShot = $userSnapShot;
    }

    public function getCashRegisterId(): string
    {
        return $this->cashRegisterId;
    }

    public function setCashRegisterId(string $cashRegisterId): void
    {
        $this->cashRegisterId = $cashRegisterId;
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
