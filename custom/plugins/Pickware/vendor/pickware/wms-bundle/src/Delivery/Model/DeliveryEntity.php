<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\DocumentBundle\Document\Model\DocumentCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProperty\Model\PickingPropertyDeliveryRecordCollection;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventCollection;
use Shopware\Core\Checkout\Document\DocumentCollection as OrderDocumentCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class DeliveryEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickingProcessId;
    protected ?PickingProcessEntity $pickingProcess = null;
    protected ?string $orderId;
    protected ?string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected string $stateId;
    protected ?StateMachineStateEntity $state = null;
    protected ?DeliveryLineItemCollection $lineItems = null;
    protected ?string $stockContainerId;
    protected ?StockContainerEntity $stockContainer = null;
    protected ?DeliveryParcelCollection $parcels = null;
    protected ?DocumentCollection $documents = null;
    protected ?OrderDocumentCollection $orderDocuments = null;
    protected ?PickingPropertyDeliveryRecordCollection $pickingPropertyRecords = null;
    protected ?DeliveryLifecycleEventCollection $lifecycleEvents = null;

    public function getPickingProcessId(): string
    {
        return $this->pickingProcessId;
    }

    public function setPickingProcessId(string $pickingProcessId): void
    {
        if ($this->pickingProcess && $this->pickingProcess->getId() !== $pickingProcessId) {
            $this->pickingProcess = null;
        }
        $this->pickingProcessId = $pickingProcessId;
    }

    public function getPickingProcess(): PickingProcessEntity
    {
        if (!$this->pickingProcess) {
            throw new AssociationNotLoadedException('pickingProcess', $this);
        }

        return $this->pickingProcess;
    }

    public function setPickingProcess(PickingProcessEntity $pickingProcess): void
    {
        $this->pickingProcess = $pickingProcess;
        $this->pickingProcessId = $pickingProcess->getId();
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        if ($orderId === null || ($this->order && $this->order->getId() !== $orderId)) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(?string $orderVersionId): void
    {
        if ($orderVersionId === null || $this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): ?OrderEntity
    {
        if ($this->orderId && !$this->order) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        if (!$order) {
            $this->orderId = null;
            $this->orderVersionId = null;
            $this->order = null;
        } else {
            $this->orderId = $order->getId();
            $this->orderVersionId = $order->getVersionId();
            $this->order = $order;
        }
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

    public function getLineItems(): DeliveryLineItemCollection
    {
        if (!$this->lineItems) {
            throw new AssociationNotLoadedException('lineItems', $this);
        }

        return $this->lineItems;
    }

    public function setLineItems(?DeliveryLineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getStockContainerId(): ?string
    {
        return $this->stockContainerId;
    }

    public function setStockContainerId(?string $stockContainerId): void
    {
        if ($this->stockContainer && $this->stockContainer->getId() !== $stockContainerId) {
            $this->stockContainer = null;
        }
        $this->stockContainerId = $stockContainerId;
    }

    public function getStockContainer(): ?StockContainerEntity
    {
        if (!$this->stockContainer && $this->stockContainerId) {
            throw new AssociationNotLoadedException('stockContainer', $this);
        }

        return $this->stockContainer;
    }

    public function setStockContainer(?StockContainerEntity $stockContainer): void
    {
        if ($stockContainer) {
            $this->stockContainerId = $stockContainer->getId();
        }
        $this->stockContainer = $stockContainer;
    }

    public function getParcels(): DeliveryParcelCollection
    {
        if (!$this->parcels) {
            throw new AssociationNotLoadedException('parcels', $this);
        }

        return $this->parcels;
    }

    public function setParcels(?DeliveryParcelCollection $parcels): void
    {
        $this->parcels = $parcels;
    }

    public function getDocuments(): DocumentCollection
    {
        if (!$this->documents) {
            throw new AssociationNotLoadedException('documents', $this);
        }

        return $this->documents;
    }

    public function setDocuments(?DocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getOrderDocuments(): OrderDocumentCollection
    {
        if (!$this->orderDocuments) {
            throw new AssociationNotLoadedException('orderDocuments', $this);
        }

        return $this->orderDocuments;
    }

    public function setOrderDocuments(?OrderDocumentCollection $orderDocuments): void
    {
        $this->orderDocuments = $orderDocuments;
    }

    public function getPickingPropertyRecords(): PickingPropertyDeliveryRecordCollection
    {
        if (!$this->pickingPropertyRecords) {
            throw new AssociationNotLoadedException('pickingPropertyRecords', $this);
        }

        return $this->pickingPropertyRecords;
    }

    public function setPickingPropertyRecords(PickingPropertyDeliveryRecordCollection $pickingPropertyRecords): void
    {
        $this->pickingPropertyRecords = $pickingPropertyRecords;
    }

    public function getLifecycleEvents(): ?DeliveryLifecycleEventCollection
    {
        if (!$this->lifecycleEvents) {
            throw new AssociationNotLoadedException('lifecycleEvents', $this);
        }

        return $this->lifecycleEvents;
    }

    public function setLifecycleEvents(?DeliveryLifecycleEventCollection $lifecycleEvents): void
    {
        $this->lifecycleEvents = $lifecycleEvents;
    }
}
