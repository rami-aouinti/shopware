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
 * @method void add(AnalyticsAggregationEntity $entity)
 * @method void set(string $key, AnalyticsAggregationEntity $entity)
 * @method AnalyticsAggregationEntity[] getIterator()
 * @method AnalyticsAggregationEntity[] getElements()
 * @method AnalyticsAggregationEntity|null get(string $key)
 * @method AnalyticsAggregationEntity|null first()
 * @method AnalyticsAggregationEntity|null last()
 */
class AnalyticsAggregationCollection extends EntityCollection {}
