<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\ControllerExceptionHandling;

use Exception;
use Pickware\ApiErrorHandlingBundle\ErrorStashingService;
use Pickware\DebugBundle\ResponseExceptionListener\JwtValidator;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Language\convertExceptionToArray;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class JsonApiStashedErrorHandler implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JwtValidator $jwtValidator,
        private readonly ErrorStashingService $errorStashingService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => [
                'onResponseEvent',
                0,
            ],
        ];
    }

    public function onResponseEvent(ResponseEvent $event): void
    {
        if ($this->errorStashingService->getTotalStashedErrorCount() === 0) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->shouldAddErrorDebugInformationToResponse($request, $response)) {
            return;
        }

        // We catch the error because we don't want to obfuscate any potential original error messages
        try {
            $content = Json::decodeToArray($response->getContent());
        } catch (Exception $error) {
            $this->logger->error(
                sprintf(
                    'Caught an json decode exception while trying to add error debug information in %s',
                    self::class,
                ),
                [
                    'responseContent' => $response->getContent(),
                    'caughtException' => $error,
                ],
            );

            return;
        }

        $content['_errors'] = array_map(
            fn(array $errorsOfCallingFunction) => array_map(convertExceptionToArray(...), $errorsOfCallingFunction),
            $this->errorStashingService->getStashedErrorsAndClearStash(),
        );
        $response->setContent(Json::stringify($content));
        $event->setResponse($response);
    }

    private function shouldAddErrorDebugInformationToResponse(Request $request, Response $response): bool
    {
        if (!$this->containsValidDebugHeader($request)) {
            return false;
        }

        if ($response->headers->get('Content-Type') !== 'application/json') {
            return false;
        }

        if ($response->getStatusCode() === 401) {
            return false;
        }

        return true;
    }

    private function containsValidDebugHeader(Request $request): bool
    {
        $debugHeader = $request->headers->get('X-Pickware-Show-Trace');

        return $debugHeader && $this->jwtValidator->isJwtTokenValid($debugHeader);
    }
}
