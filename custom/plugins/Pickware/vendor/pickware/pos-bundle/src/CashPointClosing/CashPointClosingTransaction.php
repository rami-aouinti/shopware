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

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class CashPointClosingTransaction implements JsonSerializable
{
    private string $id;
    private string $cashRegisterId;
    private ?string $cashPointClosingId;
    private string $currencyId;
    private string $userId;
    private array $userSnapshot;
    private ?string $customerId;
    private CashPointClosingTransactionBuyer $buyer;
    private int $number;
    private string $type;
    private ?string $name;
    private DateTimeInterface $start;
    private DateTimeInterface $end;
    private Price $total;
    private CashPointClosingTransactionPayment $payment;
    private ?string $comment;
    private ?array $vatTable;
    private ?array $fiscalizationContext;

    /**
     * @var CashPointClosingTransactionLineItem[]
     */
    private array $cashPointClosingTransactionLineItems;

    public function __construct() {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'cashRegisterId' => $this->cashRegisterId,
            'cashPointClosingId' => $this->cashPointClosingId,
            'currencyId' => $this->currencyId,
            'userId' => $this->userId,
            'userSnapshot' => $this->userSnapshot,
            'customerId' => $this->customerId,
            'buyer' => $this->buyer,
            'number' => $this->number,
            'type' => $this->type,
            'name' => $this->name,
            'start' => $this->start->format(DATE_ATOM),
            'end' => $this->end->format(DATE_ATOM),
            'total' => $this->total,
            'payment' => $this->payment,
            'comment' => $this->comment,
            'vatTable' => $this->vatTable,
            'fiscalizationContext' => $this->fiscalizationContext,
            'cashPointClosingTransactionLineItems' => $this->cashPointClosingTransactionLineItems,
        ];
    }

    public static function fromArray(array $array): self
    {
        $self = new self();

        $self->id = $array['id'];
        $self->cashRegisterId = $array['cashRegisterId'];
        $self->cashPointClosingId = $array['cashPointClosingId'] ?? null;
        $self->currencyId = $array['currencyId'];
        $self->userId = $array['userId'];
        $self->userSnapshot = $array['userSnapshot'];
        $self->customerId = $array['customerId'];
        $self->buyer = CashPointClosingTransactionBuyer::fromArray($array['buyer']);
        $self->number = $array['number'];
        $self->type = $array['type'];
        $self->name = $array['name'];
        $self->start = new DateTimeImmutable($array['start']);
        $self->end = new DateTimeImmutable($array['end']);
        $self->total = Price::fromArray($array['total']);
        $self->payment = CashPointClosingTransactionPayment::fromArray($array['payment']);
        $self->comment = $array['comment'] ?? null;
        $self->vatTable = $array['vatTable'] ?? null;
        $self->fiscalizationContext = $array['fiscalizationContext'] ?? null;
        $self->cashPointClosingTransactionLineItems = array_map(fn(array $payload) => CashPointClosingTransactionLineItem::fromArray($payload), $array['cashPointClosingTransactionLineItems']);

        return $self;
    }

    public function getCashRegisterId(): string
    {
        return $this->cashRegisterId;
    }

    public function setCashRegisterId(string $cashRegisterId): void
    {
        $this->cashRegisterId = $cashRegisterId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getCashPointClosingId(): ?string
    {
        return $this->cashPointClosingId;
    }

    public function setCashPointClosingId(?string $cashPointClosingId): void
    {
        $this->cashPointClosingId = $cashPointClosingId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
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
        $this->customerId = $customerId;
    }

    public function getBuyer(): CashPointClosingTransactionBuyer
    {
        return $this->buyer;
    }

    public function setBuyer(CashPointClosingTransactionBuyer $buyer): void
    {
        $this->buyer = $buyer;
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

    public function getTotal(): Price
    {
        return $this->total;
    }

    public function setTotal(Price $total): void
    {
        $this->total = $total;
    }

    public function getPayment(): CashPointClosingTransactionPayment
    {
        return $this->payment;
    }

    public function setPayment(CashPointClosingTransactionPayment $payment): void
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

    public function getFiscalizationContext(): ?array
    {
        return $this->fiscalizationContext;
    }

    public function setFiscalizationContext(?array $fiscalizationContext): void
    {
        $this->fiscalizationContext = $fiscalizationContext;
    }

    public function getCashPointClosingTransactionLineItems(): array
    {
        return $this->cashPointClosingTransactionLineItems;
    }

    public function setCashPointClosingTransactionLineItems(array $cashPointClosingTransactionLineItems): void
    {
        $this->cashPointClosingTransactionLineItems = $cashPointClosingTransactionLineItems;
    }
}
