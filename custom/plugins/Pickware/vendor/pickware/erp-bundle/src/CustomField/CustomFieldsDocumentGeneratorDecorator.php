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

use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\PickwareErpStarter\Picklist\PicklistDocumentType;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

// @phpstan-ignore class.extendsFinalByPhpDoc
#[AsDecorator(DocumentGenerator::class)]
class CustomFieldsDocumentGeneratorDecorator extends DocumentGenerator
{
    public const CUSTOM_FIELD_DOCUMENT_TYPE_TECHNICAL_NAMES = [
        InvoiceRenderer::TYPE,
        DeliveryNoteRenderer::TYPE,
        StornoRenderer::TYPE,
        InvoiceCorrectionDocumentType::TECHNICAL_NAME,
        PicklistDocumentType::TECHNICAL_NAME,
    ];

    public function __construct(
        #[AutowireDecorated]
        private readonly DocumentGenerator $decoratedInstance,
        private readonly DocumentCustomFieldService $documentCustomFieldService,
    ) {}

    public function readDocument(string $documentId, Context $context, string $deepLinkCode = '', string $fileType = PdfRenderer::FILE_EXTENSION): ?RenderedDocument
    {
        return $this->decoratedInstance->readDocument($documentId, $context, $deepLinkCode, $fileType);
    }

    public function preview(string $documentType, DocumentGenerateOperation $operation, string $deepLinkCode, Context $context): RenderedDocument
    {
        if (in_array($documentType, self::CUSTOM_FIELD_DOCUMENT_TYPE_TECHNICAL_NAMES, true)) {
            return $this->decoratedInstance->preview(
                $documentType,
                $this->addCustomFieldsToOperation($operation, $operation->getOrderId(), $documentType, $context),
                $deepLinkCode,
                $context,
            );
        }

        return $this->decoratedInstance->preview($documentType, $operation, $deepLinkCode, $context);
    }

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    public function generate(string $documentType, array $operations, Context $context): DocumentGenerationResult
    {
        if (in_array($documentType, self::CUSTOM_FIELD_DOCUMENT_TYPE_TECHNICAL_NAMES, true)) {
            $modifiedOperations = [];
            foreach ($operations as $operation) {
                $modifiedOperations[$operation->getOrderId()] = $this->addCustomFieldsToOperation($operation, $operation->getOrderId(), $documentType, $context);
            }

            return $this->decoratedInstance->generate($documentType, $modifiedOperations, $context);
        }

        return $this->decoratedInstance->generate($documentType, $operations, $context);
    }

    public function upload(string $documentId, Context $context, Request $uploadedFileRequest): DocumentIdStruct
    {
        return $this->decoratedInstance->upload($documentId, $context, $uploadedFileRequest);
    }

    private function addCustomFieldsToOperation(DocumentGenerateOperation $operation, string $orderId, string $documentTypeTechnicalName, Context $context): DocumentGenerateOperation
    {
        $config = $operation->getConfig();
        $customFieldsConfig = $this->documentCustomFieldService->getCustomFieldsConfig($orderId, $documentTypeTechnicalName, $context);
        $config = array_merge($config, $customFieldsConfig);

        $modifiedOperation = new DocumentGenerateOperation(
            $operation->getOrderId(),
            $operation->getFileType(),
            $config,
            $operation->getReferencedDocumentId(),
            $operation->isStatic(),
            $operation->isPreview(),
        );
        if ($operation->getDocumentId() !== null) {
            $modifiedOperation->setDocumentId($operation->getDocumentId());
        }

        return $modifiedOperation;
    }
}
