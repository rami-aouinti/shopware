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

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class DocumentTypeInstaller
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function installDocumentType(DocumentType $documentType, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($documentType, $context): void {
            $documentTypeId = $this->ensureDocumentTypeExists($documentType, $context);
            $this->ensureGlobalDocumentBaseConfigExists($documentType, $documentTypeId, $context);
        });
    }

    private function ensureDocumentTypeExists(
        DocumentType $documentType,
        Context $context,
    ): string {
        /** @var DocumentEntity|null $existingDocumentType */
        $existingDocumentType = $this->entityManager->findOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => $documentType->getTechnicalName()],
            $context,
        );
        $documentTypeId = $existingDocumentType ? $existingDocumentType->getId() : Uuid::randomHex();

        $this->entityManager->upsert(
            DocumentTypeDefinition::class,
            [
                [
                    'id' => $documentTypeId,
                    'technicalName' => $documentType->getTechnicalName(),
                    'name' => $documentType->getTranslations(),
                ],
            ],
            $context,
        );

        return $documentTypeId;
    }

    private function ensureGlobalDocumentBaseConfigExists(
        DocumentType $documentType,
        string $documentTypeId,
        Context $context,
    ): void {
        $existingGlobalBaseConfigs = $this->entityManager->findBy(
            DocumentBaseConfigDefinition::class,
            [
                'documentTypeId' => $documentTypeId,
                'global' => true,
            ],
            $context,
        );
        if ($existingGlobalBaseConfigs->count() > 0) {
            return;
        }

        $this->entityManager->create(
            DocumentBaseConfigDefinition::class,
            [
                [
                    'id' => Uuid::randomHex(),
                    'documentTypeId' => $documentTypeId,
                    'name' => $documentType->getTechnicalName(),
                    'filenamePrefix' => $documentType->getFilenamePrefix(),
                    'config' => $this->determineDocumentConfiguration($documentType, $context),
                    'global' => true,
                ],
            ],
            $context,
        );
    }

    private function determineDocumentConfiguration(DocumentType $documentType, Context $context): array
    {
        $config = $documentType->getConfigOverwrite();

        if ($documentType->getBaseConfigurationDocumentTypeTechnicalName()) {
            /** @var DocumentBaseConfigEntity $baseDocumentBaseConfig */
            $documentBaseConfigs = $this->entityManager->findBy(
                DocumentBaseConfigDefinition::class,
                [
                    'documentType.technicalName' => $documentType->getBaseConfigurationDocumentTypeTechnicalName(),
                    'global' => true,
                ],
                $context,
            );

            $baseConfig = $documentBaseConfigs->count() > 0 ? $documentBaseConfigs->first()->getConfig() : [];
            $config = array_merge($baseConfig, $config);
        }

        return $config;
    }
}
