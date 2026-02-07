<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\OAuth\Model\Subscriber;

use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\MobileAppAuthBundle\Installation\Steps\UpsertMobileAppAclRoleInstallationStep;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class MobileAppAuthAclRoleWriteRestrictorSubscriber implements EventSubscriberInterface
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_MOBILE_APP_AUTH_BUNDLE__WRITE_RESTRICTOR';

    public static function getSubscribedEvents(): array
    {
        return [
            EntityPreWriteValidationEventDispatcher::getEventName(AclRoleDefinition::ENTITY_NAME) => 'restrictWriteOnMobileAppAuthRole',
            EntityPreWriteValidationEventDispatcher::getEventName(AclUserRoleDefinition::ENTITY_NAME) => 'restrictWriteOnMobileAppAuthRole',
        ];
    }

    public function restrictWriteOnMobileAppAuthRole($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        $commands = $event->getCommands();
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            $entityName = $command->getEntityName();
            if (
                !(
                    $entityName === AclRoleDefinition::ENTITY_NAME
                    && $command->getPrimaryKey()['id'] === UpsertMobileAppAclRoleInstallationStep::MOBILE_APP_ACL_ROLE_ID_BIN
                ) && !(
                    $entityName === AclUserRoleDefinition::ENTITY_NAME
                    && $command->getPrimaryKey()['acl_role_id'] === UpsertMobileAppAclRoleInstallationStep::MOBILE_APP_ACL_ROLE_ID_BIN
                )
            ) {
                continue;
            }

            $message = 'The pickware mobile app auth acl role should not be deleted, updated or assigned to a user.';
            $violations->add(new ConstraintViolation(
                $message,
                $message,
                [],
                null,
                '/',
                null,
                null,
                sprintf(
                    '%s__%s',
                    self::ERROR_CODE_NAMESPACE,
                    mb_strtoupper($entityName),
                ),
            ));
        }

        if ($violations->count() > 0) {
            $event->addViolation(new WriteConstraintViolationException($violations));
        }
    }
}
