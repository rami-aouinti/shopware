<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating;

use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AdminApiJsonApiErrorExceptionLocalization implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [ResponseEvent::class => 'onResponseEvent'];
    }

    public function onResponseEvent(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($response->getStatusCode() < 400) {
            return;
        }

        /** @var Context $context */
        $context = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        // Only the Admin API uses JSON API.
        if (!$context || !($context->getSource() instanceof AdminApiSource)) {
            return;
        }

        // We catch the error because we don't want to obfuscate the original error
        try {
            $content = Json::decodeToArray($response->getContent());
        } catch (Exception $error) {
            $this->logger->error(
                sprintf(
                    'Caught an json decode exception while trying to localize error in %s',
                    self::class,
                ),
                [
                    'responseContent' => $response->getContent(),
                    'caughtException' => $error,
                ],
            );

            return;
        }

        $errors = $content['errors'] ?? null;
        if (!is_array($errors) || !array_is_list($errors) || !is_array($errors[0]) || array_is_list($errors[0])) {
            // Some other plugins might not use the JSON API errors response format. We don't want to interfere with
            // those responses.
            return;
        }

        $locales = $event->getRequest()->getLanguages();

        $userId = ContextExtension::findUserId($context);
        if ($userId !== null) {
            /** @var UserEntity $user */
            $user = $this->entityManager->getByPrimaryKey(
                UserDefinition::class,
                $userId,
                $context,
                ['locale'],
            );

            // Add the user's locale as first locale to prioritize it.
            $locales = [
                $user->getLocale()->getCode(),
                ...$locales,
            ];
        }
        $localizedErrors = array_map(
            fn(array $error) => LocalizableJsonApiError::fromArray($error)->localize($locales),
            $errors,
        );

        $content['errors'] = $localizedErrors;
        $response->setContent(Json::stringify($content));
        $event->setResponse($response);
    }
}
