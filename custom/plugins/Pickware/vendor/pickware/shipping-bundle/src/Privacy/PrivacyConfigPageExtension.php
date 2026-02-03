<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy;

use Shopware\Core\Framework\Struct\Struct;

class PrivacyConfigPageExtension extends Struct
{
    public const PAGE_EXTENSION_NAME = 'pickwareShippingPrivacyConfiguration';

    public function __construct(
        protected DataTransferPolicy $emailTransferPolicy,
    ) {}

    public function getEmailTransferPolicy(): DataTransferPolicy
    {
        return $this->emailTransferPolicy;
    }
}
