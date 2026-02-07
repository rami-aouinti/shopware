<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\Document;

use Pickware\ApiErrorHandlingBundle\ErrorStashingService;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * This decorator wraps the results from document generation in our own result class, allowing us to handle our own
 * JsonApiErrors and parse them differently when serializing to JSON format.
 *
 * See https://github.com/pickware/shopware-plugins/issues/3888 and the PickwareDocumentGenerationResult for more.
 */
class JsonApiErrorFormattingDocumentGeneratorDecorator extends DocumentGenerator
{
    public function __construct(
        private readonly DocumentGenerator $decoratedInstance,
        private readonly ErrorStashingService $errorStashingService,
    ) {}

    public function readDocument(string $documentId, Context $context, string $deepLinkCode = '', string $fileType = PdfRenderer::FILE_EXTENSION): ?RenderedDocument
    {
        return $this->decoratedInstance->readDocument($documentId, $context, $deepLinkCode, $fileType);
    }

    public function preview(string $documentType, DocumentGenerateOperation $operation, string $deepLinkCode, Context $context): RenderedDocument
    {
        return $this->decoratedInstance->preview($documentType, $operation, $deepLinkCode, $context);
    }

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    public function generate(string $documentType, array $operations, Context $context): DocumentGenerationResult
    {
        $result = $this->decoratedInstance->generate($documentType, $operations, $context);

        $this->errorStashingService->stashErrors($result->getErrors());

        return PickwareDocumentGenerationResult::fromDocumentGenerationResult($result);
    }

    public function upload(string $documentId, Context $context, Request $uploadedFileRequest): DocumentIdStruct
    {
        return $this->decoratedInstance->upload($documentId, $context, $uploadedFileRequest);
    }
}
