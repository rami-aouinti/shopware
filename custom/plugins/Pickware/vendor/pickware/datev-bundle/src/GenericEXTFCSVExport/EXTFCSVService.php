<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\GenericEXTFCSVExport;

use DateTimeInterface;
use Pickware\DatevBundle\Config\Values\ConfigValues;

/**
 * data corresponds to https://developer.datev.de/de/file-format/details/datev-format/format-description/header
 */
class EXTFCSVService
{
    /**
     * Designates the file as an export created by a third party software (in this case pickware)
     */
    public const THIRD_PARTY_EXPORT_FLAG = 'EXTF';

    public const EXPORT_VERSION = 700;
    public const RECORD_TYPE_FINANCIAL_ACCOUNTING = 1;

    /**
     * Tells DATEV that the export should not automatically be "commited"
     */
    public const FIXATION = 0;

    private const ESCAPED_CHARACTERS = [
        "\t",
        "\n",
        "\r",
    ];
    public const FILE_EXTENSION = '.csv';

    public function getEXTFCSVFileName(EXTFCSVExportFormat $format, DateTimeInterface $exportCreatedAt, ?string $salesChannelName): string
    {
        return sprintf(
            '%s_%s_%s_%s',
            self::THIRD_PARTY_EXPORT_FLAG,
            $format->getDisplayName(),
            $exportCreatedAt->format('Y-m-d-H_i_sP'),
            $this->prepareSalesChannelName($salesChannelName),
        );
    }

    private function prepareSalesChannelName(?string $salesChannelName): string
    {
        if ($salesChannelName === null) {
            return 'unknown_sales_channel';
        }

        return preg_replace(
            '/[^\\w-]/',
            '_',
            str_replace(' ', '-', mb_strtolower($salesChannelName)),
        );
    }

    /**
     * @return array<int|string>
     */
    public function getEXTFCSVFileHeader(
        EXTFCSVExportFormat $format,
        DateTimeInterface $exportCreatedAt,
        ConfigValues $datevConfig,
        DateTimeInterface $exportStartDate,
        DateTimeInterface $exportEndDate,
    ): array {
        return [
            sprintf('"%s"', self::THIRD_PARTY_EXPORT_FLAG),
            self::EXPORT_VERSION,
            $format->getCategory(),
            $format->getName(),
            $format->getVersion(),
            $exportCreatedAt->format('YmdHisv'),
            '',
            '',
            '',
            '',
            $datevConfig->getConsultantNumber(),
            $datevConfig->getClientNumber(),
            $datevConfig->getStartOfBusinessYear($exportStartDate)->format('Ymd'),
            $datevConfig->getGeneralLedgerAccountLength(),
            $exportStartDate->format('Ymd'),
            $exportEndDate->format('Ymd'),
            '',
            '',
            self::RECORD_TYPE_FINANCIAL_ACCOUNTING,
            '',
            self::FIXATION,
            '',
            '',
            '""',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    public function encodeDatevType(EXTFCSVColumnType $columnType, mixed $columnValue): string
    {
        return match ($columnType) {
            EXTFCSVColumnType::String => sprintf(
                '"%s"',
                trim(str_replace(self::ESCAPED_CHARACTERS, ' ', $columnValue)),
            ),
            EXTFCSVColumnType::Float => is_float($columnValue) ? number_format($columnValue, 2, ',', '') : '',
            EXTFCSVColumnType::Boolean => is_bool($columnValue) ? (string)(int)$columnValue : '',
            default => trim(str_replace(self::ESCAPED_CHARACTERS, ' ', (string)$columnValue)),
        };
    }
}
