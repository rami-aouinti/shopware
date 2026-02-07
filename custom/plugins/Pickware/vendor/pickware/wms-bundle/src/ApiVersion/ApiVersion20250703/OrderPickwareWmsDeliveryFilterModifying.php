<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ApiVersion\ApiVersion20250703;

use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use stdClass;

trait OrderPickwareWmsDeliveryFilterModifying
{
    /**
     * @param stdClass[] $filters
     */
    private static function replaceNoPickwareWmsDeliveryFilter(array &$filters): void
    {
        foreach ($filters as &$filter) {
            if (property_exists($filter, 'queries')) {
                self::replaceNoPickwareWmsDeliveryFilter($filter->queries);
            } elseif (property_exists($filter, 'field') && is_string($filter->field) && str_ends_with($filter->field, 'pickwareWmsDelivery.id') && $filter->value === null) {
                $pendingDeliveryFilter = new stdClass();
                $pendingDeliveryFilter->type = 'equalsAny';
                $pendingDeliveryFilter->field = preg_replace(
                    '/^(.+\\.)?pickwareWmsDelivery\\.id$/',
                    '$1pickwareWmsDeliveries.state.technicalName',
                    $filter->field,
                );
                $pendingDeliveryFilter->value = DeliveryStateMachine::PENDING_STATES;
                $filter = new stdClass();
                $filter->type = 'not';
                $filter->operator = 'and';
                $filter->queries = [$pendingDeliveryFilter];
            }
        }
        unset($filter);
    }
}
