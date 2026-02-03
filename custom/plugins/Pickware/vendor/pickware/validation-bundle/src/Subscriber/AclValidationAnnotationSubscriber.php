<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle\Subscriber;

use Pickware\ValidationBundle\Annotation\AclProtected;
use Pickware\ValidationBundle\MissingAclPrivilegeError;
use ReflectionClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A subscriber that checks whether the executed controller method is annotated with @AclProtected
 * and checks if the request has the correct privileges if so.
 */
class AclValidationAnnotationSubscriber implements EventSubscriberInterface
{
    public function __construct() {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'onKernelController',
                // Use a low priority to ensure all other subscribers like authorization and context resolving did already run.
                -1000000,
            ],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!is_array($event->getController())) {
            return;
        }

        [$controllerObject, $method] = $event->getController();

        $reflectionClass = new ReflectionClass($controllerObject);
        $method = $reflectionClass->getMethod($method);
        $aclProtectedAttributes = $method->getAttributes(AclProtected::class);

        if (empty($aclProtectedAttributes)) {
            return;
        }

        /** @var Context $context */
        $context = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $privilege = $aclProtectedAttributes[0]->newInstance()->privilege;
        if (!$context->isAllowed($privilege)) {
            $response = (new MissingAclPrivilegeError($privilege))->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
            $event->setController(fn() => $response);
        }
    }
}
