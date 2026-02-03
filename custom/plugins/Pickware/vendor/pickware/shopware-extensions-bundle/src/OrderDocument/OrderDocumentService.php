<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDocument;

use DateTimeInterface;
use Pickware\DalBundle\EntityManager;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class OrderDocumentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentGenerator $documentGenerator,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly MediaService $mediaService,
        private readonly ClockInterface $timeProvider,
    ) {}

    /**
     * @param array $options
     *          $options = [
     *              'documentNumber' => (string) Use this number instead of reserving a new one. Optional.
     *              'documentConfig' => (array) An array of document config keys to add to the document entity. Optional.
     *          ]
     */
    public function createDocumentWithTechnicalName(
        string $orderId,
        string $documentTypeTechnicalName,
        Context $context,
        array $options = [],
        ?string $documentId = null,
    ): string {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);

        // Ensure that the document type exists
        $this->entityManager->getOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => $documentTypeTechnicalName],
            $context,
        );

        $documentNumber = $options['documentNumber'] ?? null;
        if ($documentNumber === null) {
            $documentNumber = $this->numberRangeValueGenerator->getValue(
                'document_' . $documentTypeTechnicalName,
                $context,
                $order->getSalesChannelId(),
            );
        }

        $documentConfig = $options['documentConfig'] ?? [];
        $documentGenerateOperation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            $this->createDocumentConfigurationArray($documentNumber, $documentConfig),
        );
        if ($documentId !== null) {
            $documentGenerateOperation->setDocumentId($documentId);
        }
        $generationResult = $this->documentGenerator->generate(
            $documentTypeTechnicalName,
            [$orderId => $documentGenerateOperation],
            $context,
        );
        $documentIdStruct = $generationResult->getSuccess()->first();
        if ($documentIdStruct === null) {
            $error = $generationResult->getErrors()[$orderId] ?? null;

            throw OrderDocumentServiceException::noDocumentGeneratedForOrder(
                $orderId,
                $error?->getMessage(),
            );
        }

        return $documentIdStruct->getId();
    }

    /**
     * @param array $options
     *          $options = [
     *              'documentNumber' => (string) Use this number instead of reserving a new one. Optional.
     *              'documentConfig' => (array) An array of document config keys to add to the document entity. Optional.
     *          ]
     */
    public function createDocument(
        string $orderId,
        string $documentTypeId,
        Context $context,
        array $options = [],
        ?string $documentId = null,
    ): string {
        /** @var DocumentTypeEntity $documentType */
        $documentType = $this->entityManager->getByPrimaryKey(DocumentTypeDefinition::class, $documentTypeId, $context);

        return $this->createDocumentWithTechnicalName(
            $orderId,
            $documentType->getTechnicalName(),
            $context,
            $options,
            $documentId,
        );
    }

    /**
     * @param array $options
     *          $options = [
     *              'documentNumber' => (string) Use this number instead of reserving a new one. Optional.
     *              'documentConfig' => (array) An array of document config keys to add to the document entity. Optional.
     *              'documentFile'   => [
     *                  'mimeType' => (string) The mime type of the file. Required.
     *                  'extension' => (string) The file extension. Required.
     *                  'content' => (string) Required.
     *              ]
     *          ]
     */
    public function uploadDocument(
        string $orderId,
        string $documentTypeId,
        Context $context,
        array $options = [],
        ?string $documentId = null,
    ): string {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);
        /** @var DocumentTypeEntity $documentType */
        $documentType = $this->entityManager->getByPrimaryKey(DocumentTypeDefinition::class, $documentTypeId, $context);

        $documentNumber = $options['documentNumber'] ?? '';
        $documentConfig = $options['documentConfig'] ?? [];

        $documentId ??= Uuid::randomHex();

        /** @var DocumentEntity $document */
        $this->entityManager->create(
            DocumentDefinition::class,
            [
                [
                    'id' => $documentId,
                    'documentTypeId' => $documentType->getId(),
                    'fileType' => FileTypes::PDF,
                    'orderId' => $orderId,
                    'orderVersionId' => $order->getVersionId(),
                    'config' => $this->createDocumentConfigurationArray($documentNumber, $documentConfig),
                    'static' => false,
                    'deepLinkCode' => Random::getAlphanumericString(32),
                ],
            ],
            $context,
        );

        $documentIdentifier = $documentConfig['documentIdentifier'] ?? $documentNumber;

        $this->addDocumentFileToDocument(
            $documentId,
            $options['documentFile'],
            $documentType->getTechnicalName() . '_' . $documentIdentifier . '_order_' . $order->getOrderNumber(),
            $context,
        );

        return $documentId;
    }

    private function addDocumentFileToDocument(
        string $documentId,
        array $documentFile,
        string $fileName,
        Context $context,
    ): void {
        $mediaId = null;
        $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use (
            $documentFile,
            $fileName,
            &$mediaId
        ): void {
            $mediaId = $this->mediaService->saveFile(
                $documentFile['content'],
                $documentFile['extension'],
                $documentFile['mimeType'],
                $fileName,
                $context,
                'document',
            );
        });

        $this->entityManager->update(
            DocumentDefinition::class,
            [
                [
                    'id' => $documentId,
                    'documentMediaFileId' => $mediaId,
                ],
            ],
            $context,
        );
    }

    private function createDocumentConfigurationArray(?string $documentNumber, array $documentConfig): array
    {
        return [
            'documentNumber' => $documentNumber,
            'documentDate' => $this->timeProvider->now()->format(DateTimeInterface::ATOM),
            'custom' => array_replace_recursive(['invoiceNumber' => $documentNumber], $documentConfig),
        ];
    }
}
