<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Document;

use Pickware\PickwareErpStarter\Document\EntityRepositoryDecorator\ForcedVersionOrderRepositoryDecorator;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;

/**
 * When `readDocument()` is called in the original DocumentGenerator service without a rendered document, the inner
 * `generate()` method is called to generate the document. If we use the regular decorator pattern (AsDecorator), that
 * inner service will call `$this->generate()` on itself and not on this decorator, thus bypassing our override.
 * To solve this, we extend the original DocumentGenerator service directly and override the existing service in the DI with
 * this class. See erp-bundle/src/Document/DependencyInjection/DocumentGeneratorCompilerPass.php
 *
 * This approach is not optimal. But all other solutions we considered are even hackier.
 */
/** @phpstan-ignore-next-line class.extendsFinalByPhpDoc */
#[Exclude()]
class DocumentGeneratorDecorator extends DocumentGenerator
{
    /** @phpstan-ignore-next-line param.type.notValid */
    public function __construct(
        private readonly ForcedVersionOrderRepositoryDecorator $forcedVersionOrderRepositoryDecorator,
        private readonly ExistingDocumentRerenderService $existingDocumentRerenderService,
        ...$args,
    ) {
        parent::__construct(...$args);
    }

    public function preview(
        string $documentType,
        DocumentGenerateOperation $operation,
        string $deepLinkCode,
        Context $context,
    ): RenderedDocument {
        return parent::preview(
            $documentType,
            $operation,
            $deepLinkCode,
            $context,
        );
    }

    /**
     * @param array<string,DocumentGenerateOperation> $operations
     */
    public function generate(string $documentType, array $operations, Context $context): DocumentGenerationResult
    {
        $operationsWithoutMediaFiles = $this->existingDocumentRerenderService
            ->filterAndConfigureOperationsForMissingMedia($operations, $context);

        $result = parent::generate(
            $documentType,
            array_diff_key($operations, $operationsWithoutMediaFiles),
            $context,
        );

        if (!empty($operationsWithoutMediaFiles)) {
            $this->forcedVersionOrderRepositoryDecorator->runWithForcedOrderVersions(
                array_map(fn(DocumentGenerateOperation $operation) => $operation->getOrderVersionId(), $operationsWithoutMediaFiles),
                function() use ($operationsWithoutMediaFiles, $documentType, $context, $result): void {
                    $resultsOfOperationsWithoutMediaFiles = parent::generate(
                        $documentType,
                        $operationsWithoutMediaFiles,
                        $context,
                    );

                    foreach ($resultsOfOperationsWithoutMediaFiles->getSuccess() as $document) {
                        $result->addSuccess($document);
                    }

                    foreach ($resultsOfOperationsWithoutMediaFiles->getErrors() as $orderId => $error) {
                        $result->addError($orderId, $error);
                    }
                },
            );
        }

        return $result;
    }

    public function readDocument(
        string $documentId,
        Context $context,
        string $deepLinkCode = '',
        string $fileType = PdfRenderer::FILE_EXTENSION,
    ): ?RenderedDocument {
        return parent::readDocument($documentId, $context, $deepLinkCode, $fileType);
    }

    public function upload(string $documentId, Context $context, Request $uploadedFileRequest): DocumentIdStruct
    {
        return parent::upload($documentId, $context, $uploadedFileRequest);
    }
}
