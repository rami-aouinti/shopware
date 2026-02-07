<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft\Controller;

use Pickware\DalBundle\ContextFactory;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\MailDraft\MailDraft;
use Pickware\PickwareErpStarter\MailDraft\MailDraftException;
use Pickware\PickwareErpStarter\MailDraft\MailDraftService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class MailDraftController
{
    private MailDraftService $mailDraftService;
    private ContextFactory $contextFactory;

    public function __construct(
        ContextFactory $contextFactory,
        MailDraftService $mailDraftService,
    ) {
        $this->contextFactory = $contextFactory;
        $this->mailDraftService = $mailDraftService;
    }

    #[Route(path: '/api/_action/pickware-erp/mail-draft/create', methods: ['POST'])]
    public function create(Request $request, Context $context): JsonResponse
    {
        $mailTemplateId = $request->get('mailTemplateId');
        if (!$mailTemplateId || !Uuid::isValid($mailTemplateId)) {
            return ResponseFactory::createUuidParameterMissingResponse('mailTemplateId');
        }
        $templateVariables = $request->get('templateVariables', []);
        $templateContentGeneratorOptions = $request->get('templateContentGeneratorOptions', []);
        $recipients = $request->get('recipients', []);
        $recipientsBcc = $request->get('recipientsBcc', []);

        $languageId = $request->get('languageId', $request->headers->get('sw-admin-current-language', Defaults::LANGUAGE_SYSTEM));
        $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $context);

        $draft = $this->mailDraftService->create(
            $mailTemplateId,
            $recipients,
            $recipientsBcc,
            $templateVariables,
            $templateContentGeneratorOptions,
            $localizedContext,
        );

        return new JsonResponse($draft->jsonSerialize());
    }

    #[Route(path: '/api/_action/pickware-erp/mail-draft/send', methods: ['POST'])]
    public function send(Request $request, Context $context): JsonResponse
    {
        $draftParameter = $request->get('mailDraft', []);

        try {
            $this->mailDraftService->send(MailDraft::fromArray($draftParameter), $context);
        } catch (MailDraftException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse();
    }
}
