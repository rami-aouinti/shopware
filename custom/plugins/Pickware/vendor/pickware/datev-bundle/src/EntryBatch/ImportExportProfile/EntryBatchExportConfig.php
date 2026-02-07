<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch\ImportExportProfile;

use DateTime;
use DateTimeInterface;
use JsonSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class EntryBatchExportConfig implements JsonSerializable
{
    public const CONFIG_KEY_ENTRY_BATCH_RECORD_CREATOR_TECHNICAL_NAME = 'entry-batch-record-creator-technical-name';
    public const CONFIG_KEY_START_DATE = 'start-date';
    public const CONFIG_KEY_END_DATE = 'end-date';
    public const CONFIG_KEY_SALES_CHANNEL_ID = 'sales-channel-id';
    public const CONFIG_KEY_NEXT_ROW_NUMBER_TO_WRITE = 'next-row-number-to-write';
    private const REQUIRED_CONFIG_KEYS = [
        self::CONFIG_KEY_ENTRY_BATCH_RECORD_CREATOR_TECHNICAL_NAME,
        self::CONFIG_KEY_START_DATE,
        self::CONFIG_KEY_END_DATE,
        self::CONFIG_KEY_SALES_CHANNEL_ID,
    ];

    public function __construct(
        public readonly string $entryBatchRecordCreatorTechnicalName,
        public readonly DateTimeInterface $startDate,
        public readonly DateTimeInterface $endDate,
        public readonly string $salesChannelId,
        public int $nextRowNumberToWrite = 1,
    ) {}

    public static function fromExportConfig(array $exportConfig): self
    {
        return new self(
            $exportConfig[self::CONFIG_KEY_ENTRY_BATCH_RECORD_CREATOR_TECHNICAL_NAME],
            new DateTime($exportConfig[self::CONFIG_KEY_START_DATE]),
            new DateTime($exportConfig[self::CONFIG_KEY_END_DATE]),
            $exportConfig[self::CONFIG_KEY_SALES_CHANNEL_ID],
            $exportConfig[self::CONFIG_KEY_NEXT_ROW_NUMBER_TO_WRITE] ?? 1,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            self::CONFIG_KEY_ENTRY_BATCH_RECORD_CREATOR_TECHNICAL_NAME => $this->entryBatchRecordCreatorTechnicalName,
            self::CONFIG_KEY_START_DATE => $this->startDate->format(DateTimeInterface::RFC3339_EXTENDED),
            self::CONFIG_KEY_END_DATE => $this->endDate->format(DateTimeInterface::RFC3339_EXTENDED),
            self::CONFIG_KEY_SALES_CHANNEL_ID => $this->salesChannelId,
            self::CONFIG_KEY_NEXT_ROW_NUMBER_TO_WRITE => $this->nextRowNumberToWrite,
        ];
    }

    public static function validate(array $config): JsonApiErrors
    {
        $errors = JsonApiErrors::noError();
        foreach (self::REQUIRED_CONFIG_KEYS as $requiredConfigKey) {
            if (!array_key_exists($requiredConfigKey, $config)) {
                $errors->addError(ImportExportException::createConfigParameterNotSetError($requiredConfigKey));
            }
        }

        return $errors;
    }
}
