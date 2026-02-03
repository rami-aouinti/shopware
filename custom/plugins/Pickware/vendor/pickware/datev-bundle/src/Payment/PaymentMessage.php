<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Payment;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PaymentMessage extends EntryBatchLogMessage
{
    public static function createAccountUnresolvedError(string $orderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Konto für die Zahlung der Bestellung "%s" konnte nicht ermittelt werden. Die Zahlung wurde nicht exportiert.',
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the payment of the order "%s" could not be resolved. The payment was not exported.',
                    $orderNumber,
                ),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createContraAccountUnresolvedError(string $orderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Gegenkonto für die Zahlung der Bestellung "%s" konnte nicht ermittelt werden. Die Zahlung wurde nicht exportiert.',
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The contra account assignment for the payment of the order "%s" could not be resolved. The payment was not exported.',
                    $orderNumber,
                ),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createTaskNumberMaxLengthExceededWarning(
        string $taskNumber,
        string $orderNumber,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Auftragsnummer "%s" der Bestellung "%s" ist zu lang. Nummer der Bestellung wird als Fallback verwendet.',
                    $taskNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The Auftragsnummer "%s" of the order "%s" is too long. Order number will be used as fallback.',
                    $taskNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'taskNumber' => $taskNumber,
                'orderNumber' => $orderNumber,
            ],
            logLevel: ImportExportLogLevel::Warning->value,
        );
    }
}
