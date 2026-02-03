<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Values;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ConfigValues implements JsonSerializable
{
    public const POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_DOCUMENTNUMBER = 'documentnumber';
    public const POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_ORDERNUMBER = 'ordernumber';

    public function __construct(
        private readonly int $consultantNumber,
        private readonly int $clientNumber,
        private readonly int $generalLedgerAccountLength,
        private readonly DateTime $firstDayOfBusinessYear,
        private readonly array $postingRecord,
        private readonly PaymentCaptureConfigValues $paymentCapture,
        private readonly array $revenueAccounts,
        private readonly array $revenueAccountsForIntraCommunityDeliveries,
        private readonly array $revenueAccountsForThirdCountryDeliveries,
        private readonly CollectiveDebtorAccounts $collectiveDebtorAccounts,
        private readonly ClearingAccounts $clearingAccounts,
        private readonly CashMovementAccounts $cashMovementAccounts,
        private readonly bool $individualDebtorDetermination,
        private readonly CompanyCodes $companyCodes,
        private readonly CostCenters $costCenters,
    ) {}

    public function jsonSerialize(): array
    {
        $postingRecord = $this->getPostingRecord();
        if (isset($postingRecord['taskNumberSource']) && $postingRecord['taskNumberSource'] instanceof PostingRecordTaskNumberType) {
            $postingRecord['taskNumberSource'] = $postingRecord['taskNumberSource']->value;
        }

        return [
            'consultantNumber' => $this->getConsultantNumber(),
            'clientNumber' => $this->getClientNumber(),
            'generalLedgerAccountLength' => $this->getGeneralLedgerAccountLength(),
            'firstDayOfBusinessYear' => $this->getFirstDayOfBusinessYear()->format('m-d'),
            'postingRecord' => $postingRecord,
            'paymentCapture' => $this->getPaymentCapture(),
            'revenueAccounts' => $this->getRevenueAccounts(),
            'revenueAccountsForIntraCommunityDeliveries' => $this->getRevenueAccountsForIntraCommunityDeliveries(),
            'revenueAccountsForThirdCountryDeliveries' => $this->getRevenueAccountsForThirdCountryDeliveries(),
            'collectiveDebtorAccounts' => $this->getCollectiveDebtorAccounts(),
            'clearingAccounts' => $this->getClearingAccounts(),
            'cashMovementAccounts' => $this->getCashMovementAccounts(),
            'individualDebtorDetermination' => $this->isIndividualDebtorDetermination(),
            'companyCodes' => $this->getCompanyCodes(),
            'costCenters' => $this->getCostCenters(),
        ];
    }

    /**
     * Deserializes this config from an array. Any entirely unspecified value blocks (e.g. the entire revenue accounts
     * for third country deliveries configuration) will default to accounts and meta information given by the SKR04
     * standard accounts. See also: https://www.datev.de/web/de/datev-shop/material/kontenrahmen-datev-skr-04/.
     */
    public static function fromArray(array $array): self
    {
        $firstDayOfCurrentYear = (new DateTime())
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('first day of january this year')
            ->modify('midnight');
        $firstDayOfBusinessYear = isset($array['clientNumber']) ? DateTime::createFromFormat('!m-d', $array['firstDayOfBusinessYear']) : $firstDayOfCurrentYear;

        $postingRecord = $array['postingRecord'] ?? [
            'documentReference' => self::POSTING_RECORD_DOCUMENT_REFERENCE_TYPE_DOCUMENTNUMBER,
            'taskNumberSource' => PostingRecordTaskNumberType::OrderNumber,
        ];
        if (isset($postingRecord['taskNumberSource']) && is_string($postingRecord['taskNumberSource'])) {
            $postingRecord['taskNumberSource'] = PostingRecordTaskNumberType::tryFrom($postingRecord['taskNumberSource']);
        }

        return new self(
            consultantNumber: $array['consultantNumber'] ?? 9999999,
            clientNumber: $array['clientNumber'] ?? 99999,
            generalLedgerAccountLength: $array['generalLedgerAccountLength'] ?? 4,
            firstDayOfBusinessYear: $firstDayOfBusinessYear,
            postingRecord: $postingRecord,
            paymentCapture: PaymentCaptureConfigValues::fromArray($array['paymentCapture'] ?? []),
            revenueAccounts: $array['revenueAccounts'] ?? [
                'defaultAccountsByTaxRate' => [
                    '0' => 4150,
                    '7' => 4300,
                    '19' => 4400,
                ],
            ],
            revenueAccountsForIntraCommunityDeliveries: $array['revenueAccountsForIntraCommunityDeliveries'] ?? ['defaultAccount' => 4125],
            revenueAccountsForThirdCountryDeliveries: $array['revenueAccountsForThirdCountryDeliveries'] ?? ['defaultAccount' => 4120],
            collectiveDebtorAccounts: CollectiveDebtorAccounts::fromArray($array['collectiveDebtorAccounts'] ?? ['defaultAccount' => 10000]),
            clearingAccounts: ClearingAccounts::fromArray($array['clearingAccounts'] ?? ['defaultAccount' => 90000]),
            cashMovementAccounts: CashMovementAccounts::fromArray($array['cashMovementAccounts'] ?? []),
            individualDebtorDetermination: $array['individualDebtorDetermination'] ?? false,
            companyCodes: CompanyCodes::fromArray($array['companyCodes'] ?? []),
            costCenters: CostCenters::fromArray($array['costCenters'] ?? []),
        );
    }

    public function getConsultantNumber(): int
    {
        return $this->consultantNumber;
    }

    public function getClientNumber(): int
    {
        return $this->clientNumber;
    }

    public function getGeneralLedgerAccountLength(): int
    {
        return $this->generalLedgerAccountLength;
    }

    public function getFirstDayOfBusinessYear(): DateTime
    {
        return $this->firstDayOfBusinessYear;
    }

    public function getPostingRecord(): array
    {
        return $this->postingRecord;
    }

    public function getPaymentCapture(): PaymentCaptureConfigValues
    {
        return $this->paymentCapture;
    }

    public function getRevenueAccounts(): array
    {
        return $this->revenueAccounts;
    }

    public function getRevenueAccountsForIntraCommunityDeliveries(): array
    {
        return $this->revenueAccountsForIntraCommunityDeliveries;
    }

    public function getRevenueAccountsForThirdCountryDeliveries(): array
    {
        return $this->revenueAccountsForThirdCountryDeliveries;
    }

    public function getCollectiveDebtorAccounts(): CollectiveDebtorAccounts
    {
        return $this->collectiveDebtorAccounts;
    }

    public function getClearingAccounts(): ClearingAccounts
    {
        return $this->clearingAccounts;
    }

    public function getCashMovementAccounts(): CashMovementAccounts
    {
        return $this->cashMovementAccounts;
    }

    public function getStartOfBusinessYear(DateTimeInterface $referenceDate): DateTime
    {
        // Determine start date of business year
        $businessYearStartDate = new DateTime(
            $referenceDate->format('Y') . $this->getFirstDayOfBusinessYear()->format('-m-d'),
        );

        // Ensure that the start date is contained in the business year
        if ($businessYearStartDate->format('Y-m-d') > $referenceDate->format('Y-m-d')) {
            $businessYearStartDate->modify('-1 year');
        }

        return $businessYearStartDate;
    }

    public function isIndividualDebtorDetermination(): bool
    {
        return $this->individualDebtorDetermination;
    }

    public function getCompanyCodes(): CompanyCodes
    {
        return $this->companyCodes;
    }

    public function getCostCenters(): CostCenters
    {
        return $this->costCenters;
    }
}
