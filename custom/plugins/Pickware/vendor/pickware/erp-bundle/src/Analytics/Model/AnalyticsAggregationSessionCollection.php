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
 * @method void add(AnalyticsAggregationSessionEntity $entity)
 * @method void set(string $key, AnalyticsAggregationSessionEntity $entity)
 * @method AnalyticsAggregationSessionEntity[] getIterator()
 * @method AnalyticsAggregationSessionEntity[] getElements()
 * @method AnalyticsAggregationSessionEntity|null get(string $key)
 * @method AnalyticsAggregationSessionEntity|null first()
 * @method AnalyticsAggregationSessionEntity|null last()
 */
class AnalyticsAggregationSessionCollection extends EntityCollection {}
