<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch;

use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class EntryBatchRecord
{
    public const DEBIT_CREDIT_INVERSION_MAPPING = [
        EntryBatchRecord::DEBIT_IDENTIFIER => EntryBatchRecord::CREDIT_IDENTIFIER,
        EntryBatchRecord::CREDIT_IDENTIFIER => EntryBatchRecord::DEBIT_IDENTIFIER,
    ];
    public const DEBIT_IDENTIFIER = 'S';
    public const CREDIT_IDENTIFIER = 'H';

    public function __construct(
        public float $revenue,
        public readonly string $debitCreditIdentifier,
        public readonly int $account,
        public readonly int $contraAccount,
        public readonly DateTimeInterface $documentDate,
        public readonly ?string $documentField1,
        public readonly ?string $postingText,
        public readonly ?string $receiptLink,
        public readonly string $documentInfoType1,
        public readonly string $documentInfoContent1,
        public readonly ?string $documentInfoType2,
        public readonly ?string $documentInfoContent2,
        public readonly ?string $documentInfoType3,
        public readonly ?string $documentInfoContent3,
        public readonly ?string $documentInfoType4,
        public readonly ?string $documentInfoContent4,
        public readonly ?string $costCenter1,
        public readonly ?string $costCenter2,
        public readonly ?string $euCountryAndVatId,
        public readonly ?float $euTaxRate,
        public readonly ?string $additionalInformationType1,
        public readonly ?string $additionalInformationContent1,
        public readonly ?string $additionalInformationType2,
        public readonly ?string $additionalInformationContent2,
        public readonly ?string $additionalInformationType3,
        public readonly ?string $additionalInformationContent3,
        public readonly ?string $additionalInformationType4,
        public readonly ?string $additionalInformationContent4,
        public readonly bool $fixation,
        public readonly ?string $taskNumber,
    ) {}
}
