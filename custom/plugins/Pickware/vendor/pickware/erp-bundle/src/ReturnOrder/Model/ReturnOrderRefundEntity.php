<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\MoneyBundle\MoneyValue;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class ReturnOrderRefundEntity extends Entity
{
    use EntityIdTrait;

    protected MoneyValue $moneyValue;
    protected float $amount;
    protected string $currencyIsoCode;
    protected ?string $transactionId;
    protected array $transactionInformation;
    protected string $stateId;
    protected ?StateMachineStateEntity $state = null;
    protected string $returnOrderId;
    protected ?string $returnOrderVersionId;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected string $paymentMethodId;
    protected ?PaymentMethodEntity $paymentMethod = null;

    public function getMoneyValue(): MoneyValue
    {
        return $this->moneyValue;
    }

    public function setMoneyValue(MoneyValue $moneyValue): void
    {
        $this->moneyValue = $moneyValue;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrencyIsoCode(): string
    {
        return $this->currencyIsoCode;
    }

    public function setCurrencyIsoCode(string $currencyIsoCode): void
    {
        $this->currencyIsoCode = $currencyIsoCode;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getTransactionInformation(): array
    {
        return $this->transactionInformation;
    }

    public function setTransactionInformation(array $transactionInformation): void
    {
        $this->transactionInformation = $transactionInformation;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        if ($this->state && $this->state->getId() !== $stateId) {
            $this->state = null;
        }
        $this->stateId = $stateId;
    }

    public function getState(): StateMachineStateEntity
    {
        if (!$this->state) {
            throw new AssociationNotLoadedException('state', $this);
        }

        return $this->state;
    }

    public function setState(StateMachineStateEntity $state): void
    {
        $this->state = $state;
        $this->stateId = $state->getId();
    }

    public function getReturnOrderId(): string
    {
        return $this->returnOrderId;
    }

    public function setReturnOrderId(string $returnOrderId): void
    {
        if ($this->returnOrder && $this->returnOrder->getId() !== $returnOrderId) {
            $this->returnOrder = null;
        }
        $this->returnOrderId = $returnOrderId;
    }

    public function getReturnOrderVersionId(): ?string
    {
        return $this->returnOrderVersionId;
    }

    public function setReturnOrderVersionId(?string $returnOrderVersionId): void
    {
        if ($this->returnOrder && $this->returnOrder->getVersionId() !== $returnOrderVersionId) {
            $this->returnOrder = null;
        }
        $this->returnOrderVersionId = $returnOrderVersionId;
    }

    public function getReturnOrder(): ReturnOrderEntity
    {
        if (!$this->returnOrder) {
            throw new AssociationNotLoadedException('returnOrder', $this);
        }

        return $this->returnOrder;
    }

    public function setReturnOrder(ReturnOrderEntity $returnOrder): void
    {
        $this->returnOrder = $returnOrder;
        $this->returnOrderId = $returnOrder->getId();
        $this->returnOrderVersionId = $returnOrder->getVersionId();
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        if ($this->paymentMethod && $this->paymentMethod->getId() !== $paymentMethodId) {
            $this->paymentMethod = null;
        }
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        if (!$this->paymentMethod) {
            throw new AssociationNotLoadedException('paymentMethod', $this);
        }

        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
        $this->paymentMethodId = $paymentMethod->getId();
    }
}
