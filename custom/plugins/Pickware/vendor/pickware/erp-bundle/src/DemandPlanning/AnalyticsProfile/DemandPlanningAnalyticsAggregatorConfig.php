<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile;

use DateTime;
use DateTimeInterface;
use JsonSerializable;

class DemandPlanningAnalyticsAggregatorConfig implements JsonSerializable
{
    public const SALES_REFERENCE_INTERVAL_SELECTION_OPTION_CUSTOM = 'custom';
    public const SALES_REFERENCE_INTERVAL_SELECTION_OPTIONS = [
        self::SALES_REFERENCE_INTERVAL_SELECTION_OPTION_CUSTOM => null,
        '1week' => 7,
        '2weeks' => 2 * 7,
        '1month' => 30,
        '2months' => 2 * 30,
        '3months' => 3 * 30,
        '6months' => 6 * 30,
        '12months' => 12 * 30,
    ];

    public bool $showOnlyStockAtOrBelowReorderPoint;
    public bool $considerOpenOrdersInPurchaseSuggestion;
    public int $salesPredictionDays;
    public string $salesReferenceIntervalSelectionKey;
    public DateTime $salesReferenceIntervalFromDate;
    public DateTime $salesReferenceIntervalToDate;

    public function jsonSerialize(): array
    {
        $json = get_object_vars($this);
        $json['salesReferenceIntervalFromDate'] = $this->salesReferenceIntervalFromDate->format(DateTimeInterface::ATOM);
        $json['salesReferenceIntervalToDate'] = $this->salesReferenceIntervalToDate->format(DateTimeInterface::ATOM);

        return $json;
    }

    public function getReferenceSalesToPredictionFactor(): float
    {
        // Since the date interval is considered to include both start and end date, we need to add one day to the
        // number of days between the dates. (E.g. the interval 1 to 10 includes 10 days, but the difference is 9)
        $referenceIntervalNumberOfDays = (int) $this->salesReferenceIntervalToDate
                ->diff($this->salesReferenceIntervalFromDate, true)
                ->format('%a') + 1;

        return $this->salesPredictionDays / $referenceIntervalNumberOfDays;
    }
}
