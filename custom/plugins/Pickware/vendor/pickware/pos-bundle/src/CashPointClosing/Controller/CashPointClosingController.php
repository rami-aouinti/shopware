<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwarePos\CashPointClosing\CashPointClosingException;
use Pickware\PickwarePos\CashPointClosing\CashPointClosingService;
use Pickware\PickwarePos\CashPointClosing\Document\CashPointClosingDocumentContentGenerator;
use Pickware\PickwarePos\CashPointClosing\Document\CashPointClosingDocumentGenerator;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class CashPointClosingController
{
    private CashPointClosingService $cashPointClosingService;
    private CashPointClosingDocumentContentGenerator $cashPointClosingDocumentContentGenerator;
    private CashPointClosingDocumentGenerator $cashPointClosingDocumentGenerator;
    private EntityManager $entityManager;

    public function __construct(
        CashPointClosingService $cashPointClosingService,
        CashPointClosingDocumentContentGenerator $cashPointClosingDocumentContentGenerator,
        CashPointClosingDocumentGenerator $cashPointClosingDocumentGenerator,
        EntityManager $entityManager,
    ) {
        $this->cashPointClosingService = $cashPointClosingService;
        $this->cashPointClosingDocumentContentGenerator = $cashPointClosingDocumentContentGenerator;
        $this->cashPointClosingDocumentGenerator = $cashPointClosingDocumentGenerator;
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/api/_action/pickware-pos/cash-point-closing/create-preview', methods: ['GET'])]
    public function getCashPointClosingPreview(Request $request, Context $context): Response
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            return ResponseFactory::createUnsupportedContextSourceResponse(
                get_class($context->getSource()),
                [AdminApiSource::class],
            );
        }

        $cashRegisterId = $request->query->get('cashRegisterId');
        if (!$cashRegisterId || !Uuid::isValid($cashRegisterId)) {
            return ResponseFactory::createUuidParameterMissingResponse('cashRegisterId');
        }

        try {
            $cashPointClosingPreview = $this->cashPointClosingService->createCashPointClosingPreview(
                $cashRegisterId,
                $contextSource->getUserId(),
                $context,
            );
        } catch (CashPointClosingException $exception) {
            return $exception->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
        }

        return new JsonResponse(['cashPointClosingPreview' => $cashPointClosingPreview]);
    }

    #[Route(path: '/api/_action/pickware-pos/cash-point-closing/create', methods: ['POST'])]
    public function createCashPointClosing(Request $request, Context $context): Response
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            return ResponseFactory::createUnsupportedContextSourceResponse(
                get_class($context->getSource()),
                [AdminApiSource::class],
            );
        }

        $cashRegisterId = $request->get('cashRegisterId');
        if (!$cashRegisterId || !Uuid::isValid($cashRegisterId)) {
            return ResponseFactory::createUuidParameterMissingResponse('cashRegisterId');
        }
        $cashPointClosingId = $request->get('cashPointClosingId');
        if (!$cashPointClosingId || !Uuid::isValid($cashPointClosingId)) {
            return ResponseFactory::createUuidParameterMissingResponse('cashPointClosingId');
        }
        $cashAmount = $request->get('cashAmount');
        if ($cashAmount === null || !is_numeric($cashAmount)) {
            return ResponseFactory::createNumericParameterMissingResponse('cashAmount');
        }
        $transactionIds = $request->get('transactionIds');
        if (!$transactionIds || !is_array($transactionIds)) {
            return ResponseFactory::createParameterMissingResponse('transactionIds');
        }
        $number = $request->get('number');
        if (!is_int($number)) {
            return ResponseFactory::createNumericParameterMissingResponse('number');
        }

        $this->cashPointClosingService->createCashPointClosing(
            $cashPointClosingId,
            $cashRegisterId,
            $contextSource->getUserId(),
            $cashAmount,
            $transactionIds,
            $number,
            $context,
        );

        return new JsonResponse();
    }

    #[Route(
        path: '/api/pickware-pos/cash-point-closing/{cashPointClosingId}/document',
        name: 'api.pickware-pos.cash-point-closing.document',
        requirements: ['cashPointClosingId' => '[a-fA-F0-9]{32}'],
        methods: ['GET'],
    )]
    public function getDocument(Request $request, string $cashPointClosingId, Context $context): Response
    {
        $languageId = $request->query->get('languageId');
        if (!$languageId) {
            // This is backwards compatibility handling for the old "locale" parameter.
            // Fallback response if languageId is not set. It gets the first language with the provided locale,
            // which is not safe. Be sure to always send the languageId.
            $locale = $request->query->get('locale');
            if (!$locale) {
                return ResponseFactory::createParameterMissingResponse('locale and languageId');
            }
            /** @var LanguageEntity $language */
            $language = $this->entityManager->findFirstBy(
                LanguageDefinition::class,
                new FieldSorting('name', FieldSorting::DESCENDING),
                $context,
                ['locale.code' => $locale],
            );
            $languageId = $language->getId();
        }
        $templateVariables = $this->cashPointClosingDocumentContentGenerator->generateFromCashPointClosing(
            $cashPointClosingId,
            $languageId,
            $context,
        );
        $renderedDocument = $this->cashPointClosingDocumentGenerator->generate($templateVariables, $languageId, $context);

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }

    #[Route(
        path: '/api/pickware-pos/cash-point-closing/{cashRegisterId}/preview-document',
        name: 'api.pickware-pos.cash-point-closing.preview-document',
        requirements: ['cashRegisterId' => '[a-fA-F0-9]{32}'],
        methods: ['GET'],
    )]
    public function getPreviewDocument(Request $request, $cashRegisterId, Context $context): Response
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            return ResponseFactory::createUnsupportedContextSourceResponse(
                get_class($context->getSource()),
                [AdminApiSource::class],
            );
        }

        $languageId = $request->query->get('languageId');
        if (!$languageId) {
            // This is backwards compatibility handling for the old "locale" parameter.
            // Fallback response if languageId is not set. It gets the first language with the provided locale,
            // which is not safe. Be sure to always send the languageId.
            $locale = $request->query->get('locale');
            if (!$locale) {
                return ResponseFactory::createParameterMissingResponse('locale and languageId');
            }
            /** @var LanguageEntity $language */
            $language = $this->entityManager->findFirstBy(
                LanguageDefinition::class,
                new FieldSorting('name', FieldSorting::DESCENDING),
                $context,
                ['locale.code' => $locale],
            );
            $languageId = $language->getId();
        }

        $cashPointClosingPreview = $this->cashPointClosingService->createCashPointClosingPreview(
            $cashRegisterId,
            $contextSource->getUserId(),
            $context,
        );
        $templateVariables = $this->cashPointClosingDocumentContentGenerator->generateFromCashPointClosingPreview(
            $cashPointClosingPreview,
            $languageId,
            $context,
        );
        $renderedDocument = $this->cashPointClosingDocumentGenerator->generate($templateVariables, $languageId, $context);

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }
}
