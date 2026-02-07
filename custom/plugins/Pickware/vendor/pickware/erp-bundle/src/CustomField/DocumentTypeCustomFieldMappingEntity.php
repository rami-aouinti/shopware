<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\CustomField\CustomFieldEntity;

class DocumentTypeCustomFieldMappingEntity extends Entity
{
    use EntityIdTrait;

    protected string $documentTypeId;
    protected ?DocumentTypeEntity $documentType = null;
    protected string $customFieldId;
    protected ?CustomFieldEntity $customField = null;
    protected int $position;
    protected DocumentCustomFieldTargetEntityType $entityType;

    public function getDocumentTypeId(): string
    {
        return $this->documentTypeId;
    }

    public function setDocumentTypeId(string $documentTypeId): void
    {
        if ($this->documentType && $this->documentType->getId() !== $documentTypeId) {
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

    public function setDocumentType(?DocumentTypeEntity $documentType): void
    {
        if ($documentType) {
            $this->documentTypeId = $documentType->getId();
        }

        $this->documentType = $documentType;
    }

    public function getCustomFieldId(): string
    {
        return $this->customFieldId;
    }

    public function setCustomFieldId(string $customFieldId): void
    {
        if ($this->customField && $this->customField->getId() !== $customFieldId) {
            $this->customField = null;
        }

        $this->customFieldId = $customFieldId;
    }

    public function getCustomField(): CustomFieldEntity
    {
        if (!$this->customField) {
            throw new AssociationNotLoadedException('customField', $this);
        }

        return $this->customField;
    }

    public function setCustomField(?CustomFieldEntity $customField): void
    {
        if ($customField) {
            $this->customFieldId = $customField->getId();
        }

        $this->customField = $customField;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getEntityType(): DocumentCustomFieldTargetEntityType
    {
        return $this->entityType;
    }

    public function setEntityType(DocumentCustomFieldTargetEntityType $entityType): void
    {
        $this->entityType = $entityType;
    }
}
