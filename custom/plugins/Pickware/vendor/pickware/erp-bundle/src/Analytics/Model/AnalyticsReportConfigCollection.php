<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(AnalyticsReportConfigEntity $entity)
 * @method void set(string $key, AnalyticsReportConfigEntity $entity)
 * @method AnalyticsReportConfigEntity[] getIterator()
 * @method AnalyticsReportConfigEntity[] getElements()
 * @method AnalyticsReportConfigEntity|null get(string $key)
 * @method AnalyticsReportConfigEntity|null first()
 * @method AnalyticsReportConfigEntity|null last()
 */
class AnalyticsReportConfigCollection extends EntityCollection {}
