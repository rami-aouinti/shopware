<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DebugBundle\ResponseExceptionListener;

use GuzzleHttp\Psr7\Message;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Pickware\HttpUtils\Sanitizer\HttpSanitizing;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Language\convertExceptionToArray;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;

class ResponseExceptionListenerDecorator implements EventSubscriberInterface
{
    private const PICKWARE_APP_USER_AGENT_DETECTION_SUBSTRINGS = [
        'com.pickware.wms',
        'com.viison.pickware.POS',
    ];

    private ResponseExceptionListener $decoratedService;
    private PsrHttpFactory $psrHttpFactory;

    /**
     * We can't use a php type hint for the ResponseExceptionListener here since it does not implement an interface that
     * contains the non-static methods, and it could be decorated by a different plugin as well.
     *
     * @param ResponseExceptionListener $decoratedService
     */
    public function __construct(
        $decoratedService,
        private readonly LoggerInterface $errorLogger,
        private readonly JwtValidator $jwtValidator,
        private readonly HttpSanitizing $httpSanitizing,
    ) {
        $this->decoratedService = $decoratedService;
        $this->psrHttpFactory = new PsrHttpFactory(
            new Psr17Factory(),
            new Psr17Factory(),
            new Psr17Factory(),
            new Psr17Factory(),
        );
    }

    /**
     * Unfortunately, static methods can not be decorated, so we need to call the original method directly and hope that
     * no other plugin wraps this method and changes its return value.
     */
    public static function getSubscribedEvents(): array
    {
        return ResponseExceptionListener::getSubscribedEvents();
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $this->decoratedService->onKernelException($event);

        $exception = $event->getThrowable();

        if ($exception instanceof OAuthServerException) {
            return;
        }

        if ($this->shouldAddTraceToResponse($event->getRequest(), $event->getResponse())) {
            $event->setResponse($this->addTraceToResponse($event->getResponse(), $event->getThrowable()));
        }

        $psrRequest = $this->psrHttpFactory->createRequest($event->getRequest());

        if ($this->shouldLogTrace($event->getRequest())) {
            $context = convertExceptionToArray($exception);

            $context['trace'] = $this->removeArgsFromStackTrace($context['trace']);

            if (isset($context['previous'])) {
                $context['previous']['trace'] = $this->removeArgsFromStackTrace(
                    $context['previous']['trace'],
                );
            }

            $context['request'] = Message::toString($this->httpSanitizing->sanitizeRequest($psrRequest));
            $this->errorLogger->error($exception->getMessage(), $context);
        }
    }

    private function shouldAddTraceToResponse(Request $request, ?Response $response): bool
    {
        if (!$this->containsValidDebugHeader($request)) {
            return false;
        }

        if (!$response) {
            return false;
        }

        if (!($response instanceof JsonResponse)) {
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

    private function addTraceToResponse(Response $response, Throwable $throwable): Response
    {
        $content = Json::decodeToArray($response->getContent());
        $content['trace'] = $this->removeArgsFromStackTrace($throwable->getTrace());

        $response->setData($content);

        return $response;
    }

    private function shouldLogTrace(Request $request): bool
    {
        if ($this->containsValidDebugHeader($request)) {
            return true;
        }

        $userAgent = $request->headers->get('User-Agent');
        if (!$userAgent) {
            return false;
        }

        foreach (self::PICKWARE_APP_USER_AGENT_DETECTION_SUBSTRINGS as $substring) {
            if (str_contains(mb_strtolower($userAgent), mb_strtolower($substring))) {
                return true;
            }
        }

        return false;
    }

    private function removeArgsFromStackTrace(array $trace): array
    {
        // Remove args so that no credentials are logged.
        return array_map(function($element) {
            unset($element['args']);

            return $element;
        }, $trace);
    }
}
