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
 * @method void add(AnalyticsReportEntity $entity)
 * @method void set(string $key, AnalyticsReportEntity $entity)
 * @method AnalyticsReportEntity[] getIterator()
 * @method AnalyticsReportEntity[] getElements()
 * @method AnalyticsReportEntity|null get(string $key)
 * @method AnalyticsReportEntity|null first()
 * @method AnalyticsReportEntity|null last()
 */
class AnalyticsReportCollection extends EntityCollection {}
