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

interface Incompatibility
{
    /**
     * @return string the name of the incompatibility verifier service that can be used to verify if this
     * incompatibility is applicable in the current shopware installation. The service has to implement the
     * {@link IncompatibilityVerifier} interface.
     */
    public function getVerifierServiceName(): string;

    /**
     * @return array translated warnings for each supported locale
     */
    public function getTranslatedWarnings(): array;
}
