<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PaperTrail;

use Pickware\FeatureFlagBundle\DevelopmentFeatureFlag;

/**
 * DO NOT DELETE THIS DEV FEATURE FLAG CLASS
 * https://github.com/pickware/shopware-plugins/issues/11742
 *
 * Even though it is "released" (default: true), we mistakenly used it in different bundles. Now we cannot delete the
 * class without breaking those bundles. Because these bundles with a reference to this class have been released and
 * are now out there in production.
 *
 * We can only delete this class once the other bundles are released without usages and all customers have updated to
 * these versions. Affects pickware-pos, pickware-wms, pickware-erp-pro (and pickware-shopify-integration).
 *
 * @deprecated next-major Will be removed in next pickware-erp-starter-5.0.0
 */
class PaperTrailLoggingDevFeatureFlag extends DevelopmentFeatureFlag
{
    public const NAME = 'pickware-erp.dev.paper-trail-logging';

    public function __construct()
    {
        parent::__construct(self::NAME, true);
    }
}
