<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\OAuth\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\MobileAppAuthBundle\OAuth\Model\MobileAppCredentialDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

class UserExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                'pickwareMobileAppCredential',
                'id',
                'user_id',
                MobileAppCredentialDefinition::class,
                false,
            ),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return UserDefinition::class;
    }
}
