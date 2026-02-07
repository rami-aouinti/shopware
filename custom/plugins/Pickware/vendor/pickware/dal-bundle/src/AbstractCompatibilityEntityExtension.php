<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;

// Since we use this class as a compatibility layer for the EntityExtension class, we are allowed to disable the EntityExtension sniff.
// phpcs:disable ShopwarePlugins.Class.ExtendsEntityExtension

abstract class AbstractCompatibilityEntityExtension extends EntityExtension
{
    abstract protected function getEntityDefinitionClassName(): string;

    public function getDefinitionClass(): string
    {
        return $this->getEntityDefinitionClassName();
    }

    public function getEntityName(): string
    {
        return $this->getEntityDefinitionClassName()::ENTITY_NAME;
    }
}
