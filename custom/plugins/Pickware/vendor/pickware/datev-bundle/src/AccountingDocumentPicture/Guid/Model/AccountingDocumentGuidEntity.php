<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentGuidEntity extends Entity
{
    use EntityIdTrait;

    protected string $guid;
    protected string $documentId;
    protected ?DocumentEntity $document = null;
    protected ?ImportExportCollection $importExports;

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function setGuid(string $guid): void
    {
        $this->guid = $guid;
    }

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

    public function getDocument(): DocumentEntity
    {
        if (!$this->document) {
            throw new AssociationNotLoadedException('document', $this);
        }

        return $this->document;
    }

    public function setDocument(DocumentEntity $document): void
    {
        $this->documentId = $document->getId();
        $this->document = $document;
    }

    public function getImportExports(): ImportExportCollection
    {
        if (!$this->importExports) {
            throw new AssociationNotLoadedException('importExports', $this);
        }

        return $this->importExports;
    }

    public function setImportExports(ImportExportCollection $importExports): void
    {
        $this->importExports = $importExports;
    }
}
