<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\Model;

enum ReportGenerationStep: string
{
    private const GENERATION_ORDER = [
        // Keep the order of this array as this will decide the order of the execution of the generation steps
        self::ReportCreated,
        self::ReportPrepared,
        self::StocksCalculated,
        self::PurchasesCalculated,
        self::AveragePurchasePriceCalculated,
        self::StockRated,
        self::ReportSaved,
    ];

    case ReportCreated = 'report-created';
    case ReportPrepared = 'report-prepared';
    case StocksCalculated = 'stocks-calculated';
    case PurchasesCalculated = 'purchases-calculated';
    case AveragePurchasePriceCalculated = 'average-purchase-price-calculated';
    case StockRated = 'stock-rated';
    case ReportSaved = 'report-saved';

    public static function getFirst(): self
    {
        return self::GENERATION_ORDER[0];
    }

    public static function getLast(): self
    {
        return self::GENERATION_ORDER[count(self::GENERATION_ORDER) - 1];
    }

    public function getNext(): ?self
    {
        $index = array_search($this, self::GENERATION_ORDER, true);

        return self::GENERATION_ORDER[$index + 1] ?? null;
    }

    public function getProgress(): float
    {
        $index = array_search($this, self::GENERATION_ORDER, true);

        return ($index + 1) / count(self::GENERATION_ORDER);
    }

    public function isLast(): bool
    {
        return $this === self::getLast();
    }
}
