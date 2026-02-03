<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DocumentPrintingConfig\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DocumentPrintingConfigEntity extends Entity
{
    use EntityIdTrait;

    protected string $shippingMethodId;
    protected ?ShippingMethodEntity $shippingMethod = null;
    protected string $documentTypeId;
    protected ?DocumentTypeEntity $documentType = null;
    protected int $copies;

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        if ($this->shippingMethod && $this->shippingMethod->getId() !== $shippingMethodId) {
            $this->shippingMethod = null;
        }
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        if (!$this->shippingMethod) {
            throw new AssociationNotLoadedException('shippingMethod', $this);
        }

        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
        $this->shippingMethodId = $shippingMethod->getId();
    }

    public function getDocumentTypeId(): string
    {
        return $this->documentTypeId;
    }

    public function setDocumentTypeId(string $documentTypeId): void
    {
        if ($this->documentType?->getId() !== $documentTypeId) {
            $this->documentType = null;
        }
        $this->documentTypeId = $documentTypeId;
    }

    public function getDocumentType(): DocumentTypeEntity
    {
        if (!$this->documentType) {
            throw new AssociationNotLoadedException('documentType', $this);
        }

        return $this->documentType;
    }

    public function setDocumentType(DocumentTypeEntity $documentType): void
    {
        $this->documentType = $documentType;
        $this->documentTypeId = $documentType->getId();
    }

    public function getCopies(): int
    {
        return $this->copies;
    }

    public function setCopies(int $copies): void
    {
        $this->copies = $copies;
    }
}
