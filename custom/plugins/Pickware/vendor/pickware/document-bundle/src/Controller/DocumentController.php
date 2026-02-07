<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Controller;

use League\Flysystem\FilesystemOperator;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Controller\Validation\Document;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentCollection;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\DocumentUtils\Pdf\PdfMerger;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class DocumentController
{
    public function __construct(
        private readonly FilesystemOperator $privateFileSystem,
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
    ) {}

    /**
     * @deprecated tag:next-major The route pickware-document/{documentId}/contents is deprecated and will be removed. Use _action/pickware-document/{documentId}/contents instead.
     *
     * This controller actions has no authentication ("auth_required"=false) so that the documents can be downloaded
     * immediately. As in "click button in UI -> start pdf download".
     */
    #[Route(
        path: '/api/_action/pickware-document/{documentId}/contents',
        name: 'api.action.pickware-document.contents',
        requirements: ['documentId' => '[a-fA-F0-9]{32}'],
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    #[Route(
        path: '/api/pickware-document/{documentId}/contents',
        name: 'api.pickware-document.contents',
        requirements: ['documentId' => '[a-fA-F0-9]{32}'],
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    public function documentContents(string $documentId, Request $request, Context $context): Response
    {
        $deepLinkCode = $request->get('deepLinkCode');
        if ($deepLinkCode === null) {
            return ResponseFactory::createParameterMissingResponse('deepLinkCode');
        }
        /** @var DocumentEntity $document */
        $document = $this->entityManager->findOneBy(DocumentDefinition::class, [
            'id' => $documentId,
            'deepLinkCode' => $deepLinkCode,
        ], $context);
        if (!$document) {
            return ResponseFactory::createNotFoundResponse();
        }

        $stream = $this->privateFileSystem->readStream($document->getPathInPrivateFileSystem());

        $headers = [
            'Content-Length' => $document->getFileSizeInBytes(),
        ];
        if ($document->getMimeType()) {
            $headers['Content-Type'] = $document->getMimeType();
        }
        if ($document->getFileName()) {
            $download = $request->query->getBoolean('download', false);
            $headers['Content-Disposition'] = HeaderUtils::makeDisposition(
                $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                $document->getFileName(),
                // only printable ascii
                preg_replace('/[\\x00-\\x1F\\x7F-\\xFF]/', '_', $document->getFileName()),
            );
        }

        return new StreamedResponse(
            function() use ($stream): void {
                fpassthru($stream);
            },
            Response::HTTP_OK,
            $headers,
        );
    }

    /**
     * @deprecated tag:next-major The route _action/pickware-merge-documents is deprecated and will be removed. Use _action/pickware-document/merge-documents instead.
     *
     * This controller actions has no authentication ("auth_required"=false) so that the documents can be downloaded
     * immediately. As in "click button in UI -> start pdf download".
     *
     * Note: This endpoint is not oauth protected (is public without authentication). Therefore, we do not add a filter
     * or sorting parameter to not leak any internal information about the data models. If we _would_ allow
     * filter/sorting any unauthorized user could try-and-error map the data model via associations and gain information
     * about the system internals. We do not want that.
     */
    #[Route(
        path: '/api/_action/pickware-document/merge-documents',
        name: 'api.action.pickware-document.merge-documents',
        defaults: ['auth_required' => false],
        methods: ['GET'],
    )]
    #[Route(path: '/api/_action/pickware-merge-documents', defaults: ['auth_required' => false], name: 'api.action.pickware-merge-documents', methods: ['GET'])]
    public function mergeDocuments(Request $request, Context $context): Response
    {
        $deepLinkCodes = $request->get('deepLinkCodes');
        if (!$deepLinkCodes || !is_array($deepLinkCodes)) {
            return ResponseFactory::createParameterMissingResponse('deepLinkCodes');
        }

        /** @var DocumentCollection $documents */
        $documents = $this->entityManager->findBy(DocumentDefinition::class, [
            'deepLinkCode' => $deepLinkCodes,
        ], $context);

        if ($documents->count() === 0) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // The documents are fetched without sorting. They are in random order now. Sort them manually by the order of
        // the deeplink codes from the request argument (by the index of the array parameter).
        $documents->sort(
            fn(DocumentEntity $document1, DocumentEntity $document2) =>
                array_search($document1->getDeepLinkCode(), $deepLinkCodes) - array_search($document2->getDeepLinkCode(), $deepLinkCodes),
        );

        foreach ($documents as $document) {
            if ($document->getMimeType() !== 'application/pdf') {
                return (new JsonApiError([
                    'status' => Response::HTTP_BAD_REQUEST,
                    'title' => 'Document is not a pdf.',
                    'detail' => sprintf(
                        'Document with id=%s is not in supported format application/pdf.',
                        $document->getId(),
                    ),
                ]))->toJsonApiErrorResponse();
            }
        }

        // In case of only one document skip merging to save execution time
        if ($documents->count() === 1) {
            $responseContent = $this->privateFileSystem->read($documents->first()->getPathInPrivateFileSystem());
        } else {
            $pdfMerger = new PdfMerger();
            foreach ($documents as $document) {
                $pdfMerger->add($this->privateFileSystem->readStream($document->getPathInPrivateFileSystem()));
            }
            $responseContent = $pdfMerger->merge();
        }

        return new Response($responseContent, Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
    }

    #[Route(path: '/api/_action/pickware-document/save-file-as-document', methods: 'POST')]
    public function saveFileAsDocument(
        Request $request,
        Context $context,
    ): Response {
        $file = $request->files->get('file');
        $documentOptions = Json::decodeToArray(json: $request->request->get('document'));
        $documentOptions['fileName'] = $file->getClientOriginalName();
        $documentOptions['mimeType'] = $file->getClientMimeType();

        $document = $this->entityManager->findByPrimaryKey(DocumentDefinition::class, $documentOptions['id'], $context);

        // The existence of the document by the provided ID is used as an idempotency check.
        if (!$document) {
            $fileStream = fopen($file->getPathname(), 'rb');
            try {
                $this->documentContentsService->saveStreamAsDocument(
                    $fileStream,
                    $context,
                    $documentOptions,
                );
            } finally {
                if (is_resource($fileStream)) {
                    fclose($fileStream);
                }
            }
        }

        return new JsonResponse(status: 201);
    }
}
