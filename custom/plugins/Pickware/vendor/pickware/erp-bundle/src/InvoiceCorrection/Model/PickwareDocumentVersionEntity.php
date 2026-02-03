<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickwareDocumentVersionEntity extends Entity
{
    use EntityIdTrait;

    protected string $documentId;
    protected string $orderId;
    protected string $orderVersionId;
    protected ?DocumentEntity $document = null;
    protected ?OrderEntity $order = null;

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function setDocumentId(string $documentId): void
    {
        if ($this->document && $this->document->getId() !== $documentId) {
            $this->document = null;
        }
        $this->documentId = $documentId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getDocument(): ?DocumentEntity
    {
        if (!$this->document && $this->documentId) {
            throw new AssociationNotLoadedException('document', $this);
        }

        return $this->document;
    }

    public function setDocument(DocumentEntity $document): void
    {
        $this->documentId = $document->getId();
        $this->document = $document;
    }

    public function getOrder(): ?OrderEntity
    {
        if (!$this->order && $this->orderId) {
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
}
