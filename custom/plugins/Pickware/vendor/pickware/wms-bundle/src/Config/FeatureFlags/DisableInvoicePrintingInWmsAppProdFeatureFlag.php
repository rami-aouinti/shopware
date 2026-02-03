<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config\FeatureFlags;

use Pickware\FeatureFlagBundle\ProductionFeatureFlag;

/**
 * This feature flag currently only exists for our customer "Vicinity" because they need an invoice at the time of
 * creating a shipping label, but they don't want to print the invoice. Creating an invoice in a flow is problematic,
 * because it extends the execution time of for example starting a picking process unnecessarily. Especially when
 * starting a batch picking process, the invoice creation can take a long time, because it is done for all orders in the
 * batch.
 */
class DisableInvoicePrintingInWmsAppProdFeatureFlag extends ProductionFeatureFlag
{
    public const NAME = 'pickware-wms.prod.disable-invoice-printing-in-wms-app';

    public function __construct()
    {
        parent::__construct(self::NAME, isActiveOnPremises: false);
    }
}
