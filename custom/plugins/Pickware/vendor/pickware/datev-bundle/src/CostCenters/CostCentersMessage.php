<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CostCenters;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CostCentersMessage extends EntryBatchLogMessage
{
    public static function createSalesChannelCostCenterFormatNotValidMessage(
        string $costCenter,
        string $salesChannelName,
        string $documentType,
        string $documentNumber,
        string $orderNumber,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Kostenstelle "%s" des Verkaufskanals "%s" entspricht nicht den DATEV Formatvorgaben. Kostenstellen dürfen maximal 36 Zeichen lang sein. Die Kostenstelle wurde daher bei der Ermittlung der Buchungssätze für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" nicht berücksichtig. Bitte überprüfe Deine DATEV Konfiguration.',
                    $costCenter,
                    $salesChannelName,
                    $documentType,
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The cost center “%s” of sales channel “%s” does not meet the DATEV format specifications. Cost centers may be a maximum of 36 characters long. The cost center was therefore not taken into account when determining the posting records for the document “%s” with the number “%s” of the order “%s”. Please check your DATEV configuration.',
                    $costCenter,
                    $salesChannelName,
                    $documentType,
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'costCenter' => $costCenter,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_WARNING,
        );
    }

    public static function createProductCostCenterFormatNotValidMessage(
        string $costCenter,
        string $productNumber,
        string $documentType,
        string $documentNumber,
        string $orderNumber,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Kostenstelle "%s" des Produkts "%s" entspricht nicht den DATEV Formatvorgaben. Kostenstellen dürfen maximal 36 Zeichen lang sein. Die Kostenstelle wurde daher bei der Ermittlung der Buchungssätze für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" nicht berücksichtigt. Bitte überprüfe Deine DATEV Konfiguration.',
                    $costCenter,
                    $productNumber,
                    $documentType,
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The cost center “%s” of product “%s” does not meet the DATEV format specifications. Cost centers may be a maximum of 36 characters long. The cost center was therefore not taken into account when determining the posting records for the document “%s” with the number “%s” of the order “%s”. Please check your DATEV configuration.',
                    $costCenter,
                    $productNumber,
                    $documentType,
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'costCenter' => $costCenter,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_WARNING,
        );
    }
}
