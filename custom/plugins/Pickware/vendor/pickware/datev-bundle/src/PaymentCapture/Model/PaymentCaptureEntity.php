<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryEntity;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PaymentCaptureEntity extends Entity
{
    use EntityIdTrait;

    protected string $type;
    protected float $amount;
    protected ?float $originalAmount;
    protected string $currencyId;
    protected ?CurrencyEntity $currency = null;
    protected ?string $exportComment = null;
    protected ?string $internalComment = null;
    protected ?string $transactionReference = null;
    protected DateTimeInterface $transactionDate;
    protected string $orderId;
    protected string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected ?string $orderTransactionId = null;
    protected ?string $orderTransactionVersionId = null;
    protected ?OrderTransactionEntity $orderTransaction = null;
    protected ?string $stateMachineHistoryId = null;
    protected ?StateMachineHistoryEntity $stateMachineHistory = null;
    protected ?string $returnOrderRefundId = null;
    protected ?string $returnOrderRefundVersionId = null;
    protected ?ReturnOrderRefundEntity $returnOrderRefund = null;
    protected ?string $userId = null;
    protected ?array $userSnapshot = null;
    protected ?UserEntity $user = null;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getOriginalAmount(): ?float
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(?float $originalAmount): void
    {
        $this->originalAmount = $originalAmount;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        if ($this->currency && $this->currency->getId() !== $currencyId) {
            $this->currency = null;
        }

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
        $this->currencyId = $currency->getId();
        $this->currency = $currency;
    }

    public function getExportComment(): ?string
    {
        return $this->exportComment;
    }

    public function setExportComment(?string $exportComment): void
    {
        $this->exportComment = $exportComment;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function setInternalComment(?string $internalComment): void
    {
        $this->internalComment = $internalComment;
    }

    public function getTransactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function setTransactionReference(?string $transactionReference): void
    {
        $this->transactionReference = $transactionReference;
    }

    public function getTransactionDate(): DateTimeInterface
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(DateTimeInterface $transactionDate): void
    {
        $this->transactionDate = $transactionDate;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderId(string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): OrderEntity
    {
        if (!$this->order) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->orderId = $order->getId();
        $this->orderVersionId = $order->getVersionId();
        $this->order = $order;
    }

    public function getOrderTransactionId(): ?string
    {
        return $this->orderTransactionId;
    }

    public function getOrderTransactionVersionId(): ?string
    {
        return $this->orderTransactionVersionId;
    }

    public function setOrderTransactionId(?string $orderTransactionId): void
    {
        if ($this->orderTransaction && $this->orderTransaction->getId() !== $orderTransactionId) {
            $this->orderTransaction = null;
        }
        $this->orderTransactionId = $orderTransactionId;
    }

    public function setOrderTransactionVersionId(?string $orderTransactionVersionId): void
    {
        if ($this->orderTransaction && $this->orderTransaction->getVersionId() !== $orderTransactionVersionId) {
            $this->orderTransaction = null;
        }
        $this->orderTransactionVersionId = $orderTransactionVersionId;
    }

    public function getOrderTransaction(): ?OrderTransactionEntity
    {
        if (!$this->orderTransaction && $this->orderTransactionId !== null) {
            throw new AssociationNotLoadedException('orderTransaction', $this);
        }

        return $this->orderTransaction;
    }

    public function setOrderTransaction(?OrderTransactionEntity $orderTransaction): void
    {
        $this->orderTransactionId = $orderTransaction?->getId();
        $this->orderTransactionVersionId = $orderTransaction?->getVersionId();
        $this->orderTransaction = $orderTransaction;
    }

    public function getStateMachineHistoryId(): ?string
    {
        return $this->stateMachineHistoryId;
    }

    public function setStateMachineHistoryId(?string $stateMachineHistoryId): void
    {
        if ($this->stateMachineHistory && $this->stateMachineHistory->getId() !== $stateMachineHistoryId) {
            $this->stateMachineHistory = null;
        }
        $this->stateMachineHistoryId = $stateMachineHistoryId;
    }

    public function getStateMachineHistory(): ?StateMachineHistoryEntity
    {
        if (!$this->stateMachineHistory && $this->stateMachineHistoryId !== null) {
            throw new AssociationNotLoadedException('stateMachineHistory', $this);
        }

        return $this->stateMachineHistory;
    }

    public function setStateMachineHistory(?StateMachineHistoryEntity $stateMachineHistory): void
    {
        $this->stateMachineHistoryId = $stateMachineHistory?->getId();
        $this->stateMachineHistory = $stateMachineHistory;
    }

    public function getReturnOrderRefundId(): ?string
    {
        return $this->returnOrderRefundId;
    }

    public function setReturnOrderRefundId(?string $returnOrderRefundId): void
    {
        if ($this->returnOrderRefund && $this->returnOrderRefund->getId() !== $returnOrderRefundId) {
            $this->returnOrderRefund = null;
        }
        $this->returnOrderRefundId = $returnOrderRefundId;
    }

    public function getReturnOrderRefundVersionId(): ?string
    {
        return $this->returnOrderRefundVersionId;
    }

    public function setReturnOrderRefundVersionId(?string $returnOrderRefundVersionId): void
    {
        if ($this->returnOrderRefund && $this->returnOrderRefund->getVersionId() !== $returnOrderRefundVersionId) {
            $this->returnOrderRefund = null;
        }
        $this->returnOrderRefundVersionId = $returnOrderRefundVersionId;
    }

    public function getReturnOrderRefund(): ?ReturnOrderRefundEntity
    {
        if (!$this->returnOrderRefund && $this->returnOrderRefundId !== null) {
            throw new AssociationNotLoadedException('returnOrderRefund', $this);
        }

        return $this->returnOrderRefund;
    }

    public function setReturnOrderRefund(?ReturnOrderRefundEntity $returnOrderRefund): void
    {
        $this->returnOrderRefundId = $returnOrderRefund?->getId();
        $this->returnOrderRefundVersionId = $returnOrderRefund?->getVersionId();
        $this->returnOrderRefund = $returnOrderRefund;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUserSnapshot(): ?array
    {
        return $this->userSnapshot;
    }

    public function setUserSnapshot(?array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
    }

    public function getUser(): ?UserEntity
    {
        if (!$this->user && $this->userId !== null) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->userId = $user?->getId();
        $this->user = $user;
    }
}
