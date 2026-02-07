<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;

class StockValuationError extends LocalizableJsonApiError
{
    public static function reportDoesNotFullyIncludeReportingDay(): self
    {
        return new self([
            'title' => [
                'de' => 'Bericht umfasst nicht den gesamten Berichtstag',
                'en' => 'Report does not fully include reporting day',
            ],
            'detail' => [
                'de' => 'Ein Bericht kann nur persistiert werden, wenn der Berichtstag vollständig enthalten ist.',
                'en' => 'A report can only be persisted if the reporting day is fully included.',
            ],
        ]);
    }

    public static function reportNotCompletelyGenerated(): self
    {
        return new self([
            'title' => [
                'de' => 'Bericht nicht vollständig erzeugt',
                'en' => 'Report not completely generated',
            ],
            'detail' => [
                'de' => 'Ein Bericht muss vollständig erzeugt werden, bevor er persistiert werden kann.',
                'en' => 'A report must be generated completely before it can be persisted.',
            ],
        ]);
    }

    public static function youngerReportExists(): self
    {
        return new self([
            'title' => [
                'de' => 'Jüngerer Bericht liegt bereits vor',
                'en' => 'Younger report already exists',
            ],
            'detail' => [
                'de' => 'Es existiert bereits ein anderer Bericht, dessen Bewertungszeitraum den angegebenen Stichtag umfasst.',
                'en' => 'There already exists another report whose valuation period includes the specified reporting day.',
            ],
        ]);
    }

    public static function reportCannotBeRegenerated(): self
    {
        return new self([
            'title' => [
                'de' => 'Bericht kann nicht neu generiert werden',
                'en' => 'Report cannot be regenerated',
            ],
            'detail' => [
                'de' => 'Ein Bericht kann nicht neu generiert werden.',
                'en' => 'A report cannot be regenerated.',
            ],
        ]);
    }

    public static function olderReportInWarehouseCannotBeDeleted(): self
    {
        return new self([
            'title' => [
                'de' => 'Älterer Bericht im Lager kann nicht gelöscht werden',
                'en' => 'Older report in warehouse cannot be deleted',
            ],
            'detail' => [
                'de' => 'Für dieses Lager gibt es bereits einen neueren Bericht, der auf diesem Bericht aufbaut',
                'en' => 'There exists a more recent report for this warehouse that depends on this report',
            ],
        ]);
    }
}
