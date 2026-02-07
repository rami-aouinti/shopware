<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Acl\Subscriber;

use Pickware\PickwarePos\Acl\PickwarePosFeaturePermissionsProvider;
use Shopware\Core\Framework\Api\Acl\Event\AclGetAdditionalPrivilegesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PosAclPrivilegeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AclGetAdditionalPrivilegesEvent::class => 'getAdditionalPrivileges',
        ];
    }

    public function getAdditionalPrivileges(AclGetAdditionalPrivilegesEvent $event): void
    {
        $event->setPrivileges(array_merge(
            $event->getPrivileges(),
            [PickwarePosFeaturePermissionsProvider::SPECIAL_POS_PRIVILEGE],
        ));
    }
}
