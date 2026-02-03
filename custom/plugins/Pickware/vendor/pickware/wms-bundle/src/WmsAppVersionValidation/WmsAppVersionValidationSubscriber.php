<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\WmsAppVersionValidation;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class WmsAppVersionValidationSubscriber implements EventSubscriberInterface
{
    private const REQUIRED_MINIMUM_APP_VERSION = '1.9.0';

    public function __construct() {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'validateAppVersion',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST,
            ],
        ];
    }

    public function validateAppVersion(ControllerEvent $event): void
    {
        $userAgentString = $event->getRequest()->headers->get('user-agent');
        if ($userAgentString === null) {
            return;
        }

        // Expected string format: "WMS/1.8.4 (com.pickware.wms; build:202305151616; iOS 16.5.0) Alamofire/5.6.3"
        // Since all requests are caught, we need to make sure that the request was sent by the WMS app
        $pattern = '|^WMS/(\\d+\\.\\d+\\.\\d+)|';
        if (!preg_match($pattern, $userAgentString, $matches)) {
            return;
        }

        // We allow the app to authenticate first because otherwise the app might obfuscate the provided error.
        /** @var Context $context */
        $context = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context || !ContextExtension::hasUser($context)) {
            return;
        }

        $receivedAppVersion = $matches[1];
        if (version_compare($receivedAppVersion, self::REQUIRED_MINIMUM_APP_VERSION, '<')) {
            $apiError = new JsonApiError([
                'status' => 400,
                'code' => 'PICKWARE_WMS__USER_AGENT_VALIDATION__OUTDATED_APP_VERSION',
                'title' => 'Your app version is outdated',
                'detail' => 'This shop requires a newer version of the app. Please update your app through the App Store.',
            ]);

            $event->setController(fn() => $apiError->toJsonApiErrorResponse());
        }
    }
}
