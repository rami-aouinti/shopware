<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection\Controller;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\DalBundle\EntityManager;
use function Pickware\PhpStandardLibrary\Language\makeSentence;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionConfigGenerator;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionException;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class InvoiceCorrectionController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InvoiceCorrectionConfigGenerator $invoiceCorrectionConfigGenerator,
    ) {}

    #[Route(path: '/api/_action/pickware-erp/can-invoice-correction-be-created-for-order', methods: ['POST'])]
    public function canInvoiceCorrectionBeCreatedForOrder(
        #[JsonParameterAsUuid] string $orderId,
        Request $request,
        Context $context,
    ): Response {
        try {
            $this->invoiceCorrectionConfigGenerator->getReferencedDocumentConfiguration(
                $orderId,
                $context,
            );
        } catch (InvoiceCorrectionException $e) {
            $locales = $request->getLanguages();

            $userId = ContextExtension::findUserId($context);
            if ($userId !== null) {
                /** @var UserEntity $user */
                $user = $this->entityManager->getByPrimaryKey(
                    UserDefinition::class,
                    $userId,
                    $context,
                    ['locale'],
                );

                $locales = [
                    $user->getLocale()->getCode(),
                    ...$locales,
                ];
            }

            $localizableErrors = array_map(LocalizableJsonApiError::createFromJsonApiError(...), $e->serializeToJsonApiErrors()->getErrors());
            $messages = array_map(
                fn(LocalizableJsonApiError $error) => makeSentence($error->localize($locales)->getDetail()),
                $localizableErrors,
            );

            return new JsonResponse([
                'creatable' => false,
                'reason' => implode(' ', $messages),
            ]);
        }

        return new JsonResponse(['creatable' => true]);
    }
}
