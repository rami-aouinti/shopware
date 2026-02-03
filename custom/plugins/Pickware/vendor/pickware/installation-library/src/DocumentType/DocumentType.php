<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\DocumentType;

use InvalidArgumentException;

class DocumentType
{
    /**
     * Name translations of the document type per locale code. E.g.:
     * [
     *   'de-DE' => 'Mein Dokument',
     *   'en-GB' => 'My Document',
     * ]
     */
    private array $translations;

    /**
     * Configuration values that will be written into a new document configuration, or merged into an existing document
     * configuration that was copied. See DocumentTypeInstaller::installDocumentType()
     */
    private array $configOverwrite;

    private string $technicalName;
    private string $filenamePrefix;
    private ?string $baseConfigurationDocumentTypeTechnicalName;

    public function __construct(
        string $technicalName,
        array $translations,
        string $filenamePrefix,
        ?string $baseConfigurationDocumentTypeTechnicalName = null,
        array $configOverwrite = [],
    ) {
        if (!array_key_exists('de-DE', $translations) || !array_key_exists('en-GB', $translations)) {
            throw new InvalidArgumentException(
                'Document type translations must support locale codes "de-DE" and "en-GB"',
            );
        }

        $this->technicalName = $technicalName;
        $this->translations = $translations;
        $this->filenamePrefix = $filenamePrefix;
        $this->baseConfigurationDocumentTypeTechnicalName = $baseConfigurationDocumentTypeTechnicalName;
        $this->configOverwrite = $configOverwrite;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getFilenamePrefix(): string
    {
        return $this->filenamePrefix;
    }

    public function getBaseConfigurationDocumentTypeTechnicalName(): ?string
    {
        return $this->baseConfigurationDocumentTypeTechnicalName;
    }

    public function getConfigOverwrite(): array
    {
        return $this->configOverwrite;
    }
}
