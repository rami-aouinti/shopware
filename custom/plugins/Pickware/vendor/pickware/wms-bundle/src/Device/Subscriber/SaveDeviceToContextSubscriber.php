<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Device\Subscriber;

use Pickware\PickwareWms\Device\Device;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SaveDeviceToContextSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                [
                    'enrichContextWithPickwareDevice',
                    KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST,
                ],
            ],
        ];
    }

    public function enrichContextWithPickwareDevice(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $context = ContextExtension::findFromRequest($request);
        $device = Device::fromRequest($request);
        if (!$device || !$context) {
            return;
        }

        $device->addToContext($context);
    }
}
