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

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_incompatibility_bundle.incompatibility_verifier')]
interface IncompatibilityVerifier
{
    /**
     * Checks if the given incompatibilities are applicable with the current shopware installation.
     * @param Incompatibility[] $incompatibilities list of possible incompatibilities
     * @return Incompatibility[] all applicable incompatibilities
     */
    public function verifyIncompatibilities(array $incompatibilities, Context $context): array;
}
