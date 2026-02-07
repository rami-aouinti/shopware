<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation;

use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\AccountAssignment\AccountDeterminationType;
use Pickware\DatevBundle\Config\AccountAssignment\Item\AccountAssignment;
use Pickware\DatevBundle\Config\AccountAssignment\Item\AccountRequestItem;
use Pickware\DatevBundle\IndividualDebtorAccountInformation\Model\IndividualDebtorAccountInformationDefinition;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Framework\Context;

class ExportedIndividualDebtorService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @template Item of AccountRequestItem
     * @param ImmutableCollection<AccountAssignment<Item>> $accountAssignments
     * @return ?array<string, string>
     */
    public function getIndividualDebtorAccountInformationMap(
        ImmutableCollection $accountAssignments,
        ?string $customerId,
    ): ?array {
        /** @var ImmutableCollection<int> $assignedIndividualDebtorAccounts */
        $assignedIndividualDebtorAccounts = $accountAssignments
            ->filter(fn(AccountAssignment $accountAssignment) =>
                $accountAssignment->getAccountDetermination()->getAccount() !== null
                && $accountAssignment->getAccountDetermination()->getType() === AccountDeterminationType::IndividualDebtor)
            ->map(fn(AccountAssignment $accountAssignment) => $accountAssignment->getAccountDetermination()->getAccount())
            ->deduplicate();

        if ($assignedIndividualDebtorAccounts->count() > 1) {
            throw new LogicException(
                'There should never be more than one individual debtor account assigned per document',
            );
        }

        if ($assignedIndividualDebtorAccounts->count() === 0) {
            return null;
        }

        if ($customerId === null) {
            throw new LogicException('There should always be a customer ID when an individual debtor account is assigned');
        }

        return [$customerId => $assignedIndividualDebtorAccounts->first()];
    }

    /**
     * @param array<?array<string, string>> $individualDebtorAccountInformationMaps
     */
    public function ensureIndividualDebtorAccountInformationExistsForAccountsAndExport(
        array $individualDebtorAccountInformationMaps,
        string $exportId,
        Context $context,
    ): void {
        $individualDebtorAccountInformationMaps = array_reduce(
            array_filter($individualDebtorAccountInformationMaps),
            function(array $carry, array $map) {
                foreach ($map as $customerId => $account) {
                    $carry[$customerId] ??= $account;
                }

                return $carry;
            },
            [],
        );

        $existingAccountsForExport = $this->entityManager->findBy(
            IndividualDebtorAccountInformationDefinition::class,
            [
                'importExportId' => $exportId,
                'customerId' => array_keys($individualDebtorAccountInformationMaps),
            ],
            $context,
        );

        $payloads = [];
        foreach ($individualDebtorAccountInformationMaps as $customerId => $account) {
            if ($existingAccountsForExport->filterByProperty('customerId', $customerId)->count() > 0) {
                continue;
            }

            $payloads[] = [
                'customerId' => $customerId,
                'account' => $account,
                'importExportId' => $exportId,
            ];
        }

        $this->entityManager->create(
            IndividualDebtorAccountInformationDefinition::class,
            $payloads,
            $context,
        );
    }

    public function getIndividualDebtorAccountInformationCountForExport(
        string $exportId,
        Context $context,
    ): int {
        return $this->entityManager->count(
            IndividualDebtorAccountInformationDefinition::class,
            'account',
            ['importExportId' => $exportId],
            $context,
        );
    }
}
