<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\IncompatibilityBundle\Incompatibility;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implement this interface to register new incompatibility checks. All checks are collected and processed
 * automatically, any incompatibilities that are active will be collected and displayed in the admin dashboard.
 * If your bundle does not support autoconfiguration, you have to manually tag your service.
 */
#[AutoconfigureTag('pickware_incompatibility_bundle.incompatibility_provider')]
interface IncompatibilityProvider
{
    /**
     * @return Incompatibility[] a collection of incompatibilities checks
     */
    public function getIncompatibilities(): array;
}
