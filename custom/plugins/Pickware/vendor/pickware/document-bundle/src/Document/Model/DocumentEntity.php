<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\DocumentBundle\Document\PageFormat;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DocumentEntity extends Entity
{
    use EntityIdTrait;

    public const ORIENTATION_PORTRAIT = 'portrait';
    public const ORIENTATION_LANDSCAPE = 'landscape';
    public const ORIENTATIONS = [
        self::ORIENTATION_PORTRAIT,
        self::ORIENTATION_LANDSCAPE,
    ];

    protected string $deepLinkCode;
    protected ?PageFormat $pageFormat;
    protected ?DocumentTypeEntity $documentType = null;
    protected string $documentTypeTechnicalName;
    protected ?string $orientation;
    protected ?string $mimeType;
    protected int $fileSizeInBytes;
    protected string $pathInPrivateFileSystem;
    protected ?string $fileName;

    public function getDeepLinkCode(): string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
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
        $this->documentTypeTechnicalName = $documentType->getTechnicalName();
    }

    public function getDocumentTypeTechnicalName(): string
    {
        return $this->documentTypeTechnicalName;
    }

    public function setDocumentTypeTechnicalName(string $documentTypeTechnicalName): void
    {
        if ($this->documentType && $this->documentType->getTechnicalName() !== $documentTypeTechnicalName) {
            $this->documentType = null;
        }

        $this->documentTypeTechnicalName = $documentTypeTechnicalName;
    }

    public function getPageFormat(): ?PageFormat
    {
        return $this->pageFormat;
    }

    public function setPageFormat(?PageFormat $pageFormat): void
    {
        $this->pageFormat = $pageFormat;
    }

    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    public function setOrientation(?string $orientation): void
    {
        $this->orientation = $orientation;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileSizeInBytes(): int
    {
        return $this->fileSizeInBytes;
    }

    public function setFileSizeInBytes(int $fileSizeInBytes): void
    {
        $this->fileSizeInBytes = $fileSizeInBytes;
    }

    public function getPathInPrivateFileSystem(): string
    {
        return $this->pathInPrivateFileSystem;
    }

    public function setPathInPrivateFileSystem(string $pathInPrivateFileSystem): void
    {
        $this->pathInPrivateFileSystem = $pathInPrivateFileSystem;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): void
    {
        $this->fileName = $fileName;
    }
}
