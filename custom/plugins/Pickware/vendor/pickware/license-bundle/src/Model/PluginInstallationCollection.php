<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PluginInstallationEntity $entity)
 * @method void set(string $key, PluginInstallationEntity $entity)
 * @method PluginInstallationEntity[] getIterator()
 * @method PluginInstallationEntity[] getElements()
 * @method PluginInstallationEntity|null get(string $key)
 * @method PluginInstallationEntity|null first()
 * @method PluginInstallationEntity|null last()
 *
 * @extends EntityCollection<PluginInstallationEntity>
 */
class PluginInstallationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PluginInstallationEntity::class;
    }
}
