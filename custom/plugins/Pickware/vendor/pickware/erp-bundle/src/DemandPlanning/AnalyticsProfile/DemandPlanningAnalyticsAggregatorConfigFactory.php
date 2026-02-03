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

use DateInterval;
use DateTime;
use InvalidArgumentException;
use JsonSerializable;
use Pickware\PickwareErpStarter\Analytics\AnalyticsAggregatorConfigFactory;
use Pickware\PickwareErpStarter\Analytics\AnalyticsException;

class DemandPlanningAnalyticsAggregatorConfigFactory implements AnalyticsAggregatorConfigFactory
{
    public function createAggregatorConfigFromArray(array $serialized): JsonSerializable
    {
        $config = new DemandPlanningAnalyticsAggregatorConfig();

        $missingKeys = array_diff(
            array_keys(get_class_vars(DemandPlanningAnalyticsAggregatorConfig::class)),
            array_keys(array_filter($serialized, fn($property) => isset($serialized[$property]), ARRAY_FILTER_USE_KEY)),
        );
        if (count($missingKeys) > 0) {
            throw AnalyticsException::aggregatorConfigIsMissingRequiredProperties(
                DemandPlanningAnalyticsAggregation::TECHNICAL_NAME,
                $missingKeys,
            );
        }

        $config->showOnlyStockAtOrBelowReorderPoint = (bool) $serialized['showOnlyStockAtOrBelowReorderPoint'];
        $config->considerOpenOrdersInPurchaseSuggestion = (bool) $serialized['considerOpenOrdersInPurchaseSuggestion'];
        $config->salesPredictionDays = (int) $serialized['salesPredictionDays'];
        $config->salesReferenceIntervalSelectionKey = (string) $serialized['salesReferenceIntervalSelectionKey'];
        $config->salesReferenceIntervalFromDate = new DateTime($serialized['salesReferenceIntervalFromDate']);
        $config->salesReferenceIntervalToDate = new DateTime($serialized['salesReferenceIntervalToDate']);

        // If the sales reference interval selection is _not_ the 'custom' interval selection, recalculate the interval
        // dates based on the reference interval selection anew (e.g. the last 7 days).
        if ($config->salesReferenceIntervalSelectionKey !== DemandPlanningAnalyticsAggregatorConfig::SALES_REFERENCE_INTERVAL_SELECTION_OPTION_CUSTOM) {
            $config->salesReferenceIntervalFromDate = self::getFromDateFromSalesIntervalSelectionKey(
                $config->salesReferenceIntervalSelectionKey,
            );
            $config->salesReferenceIntervalToDate = new DateTime();
        }

        return $config;
    }

    public function createDefaultAggregatorConfig(): JsonSerializable
    {
        return $this->createAggregatorConfigFromArray([
            'showOnlyStockAtOrBelowReorderPoint' => false,
            'considerOpenOrdersInPurchaseSuggestion' => true,
            'salesPredictionDays' => 30,
            'salesReferenceIntervalSelectionKey' => '1month',
            'salesReferenceIntervalFromDate' => '',
            'salesReferenceIntervalToDate' => '',
        ]);
    }

    public function getAggregationTechnicalName(): string
    {
        return DemandPlanningAnalyticsAggregation::TECHNICAL_NAME;
    }

    private static function getFromDateFromSalesIntervalSelectionKey(string $key): DateTime
    {
        if (!array_key_exists($key, DemandPlanningAnalyticsAggregatorConfig::SALES_REFERENCE_INTERVAL_SELECTION_OPTIONS)) {
            throw new InvalidArgumentException(sprintf('Unknown sales reference interval selection key "%s"', $key));
        }

        // Since the date interval is considered to include both start and end date, we need to subtract one day from
        // the number of days in the interval. (E.g. the interval 1 to 10 includes 10 days, but the difference is 9)
        $fromDate = new DateTime();
        $salesReferenceIntervalInDays = DemandPlanningAnalyticsAggregatorConfig::SALES_REFERENCE_INTERVAL_SELECTION_OPTIONS[$key];
        $fromDate->sub(new DateInterval(
            sprintf('P%sD', ($salesReferenceIntervalInDays - 1)),
        ));

        return $fromDate;
    }
}
