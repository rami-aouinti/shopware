<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\UsageReport;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\UsageReportBundle\ApiClient\Model\UsageReport;
use Pickware\UsageReportBundle\ApiClient\Model\UsageReportRegistrationResponse;
use Pickware\UsageReportBundle\ApiClient\UsageReportApiClientException;
use Pickware\UsageReportBundle\ApiClient\UsageReportApiClientInterface;
use Pickware\UsageReportBundle\Configuration\UsageReportConfiguration;
use Pickware\UsageReportBundle\Model\UsageReportCollection;
use Pickware\UsageReportBundle\Model\UsageReportDefinition;
use Pickware\UsageReportBundle\Model\UsageReportEntity;
use Pickware\UsageReportBundle\OrderReport\UsageReportOrderInitializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class UsageReportService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly UsageReportApiClientInterface $usageReportApiClient,
        private readonly ClockInterface $timeProvider,
        private readonly UsageReportOrderInitializer $usageReportOrderInitializer,
        private readonly UsageReportInitializer $usageReportInitializer,
        private readonly UsageReportOrderUpdater $usageReportOrderUpdater,
        private readonly UsageReportUpdater $usageReportUpdater,
        private readonly UsageReportErrorHandlerInterface $usageReportErrorHandler,
        private readonly UsageReportConfiguration $usageReportConfiguration,
        private readonly int $reportingPeriodInDays,
    ) {}

    public function reportUsage(): void
    {
        $context = Context::createDefaultContext();

        $this->usageReportOrderInitializer->ensureUsageReportOrdersExistForAllOrders();

        $now = $this->timeProvider->now()->setTimezone(new DateTimeZone('UTC'));
        $nowRoundedDownToWholeHour = $now->setTime((int) $now->format('G'), 0, 0, 0);
        $periodEndDate = $nowRoundedDownToWholeHour;
        $periodStartDate = $periodEndDate->sub(DateInterval::createFromDateString("{$this->reportingPeriodInDays} days"));

        $this->usageReportInitializer->ensureUsageReportsExistForPeriod($periodStartDate, $this->reportingPeriodInDays);
        $this->usageReportOrderUpdater->assignUsageReportsToUsageReportOrdersWithoutUsageReports($periodStartDate);
        $this->usageReportUpdater->updateOrderCountsForUnreportedUsageReports($periodStartDate, $periodEndDate);

        $this->reportUnreportedUsageReports($context);
    }

    private function reportUnreportedUsageReports(Context $context): void
    {
        // 482 usage reports are just shy of 100kb which is the max size of a request body in the usage report API
        $batchSize = 400;

        /** @var string[] $unreportedUsageReportIds */
        $unreportedUsageReportIds = $this->entityManager->findIdsBy(
            UsageReportDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsFilter('reportedAt', null))
                ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                    new EqualsFilter('orderCount', null),
                ]))
                ->addSorting(new FieldSorting('usageIntervalInclusiveStart', FieldSorting::ASCENDING)),
            $context,
        );

        $idBatches = array_chunk($unreportedUsageReportIds, $batchSize);
        $lastRegistrationException = null;

        foreach ($idBatches as $batchIds) {
            /** @var UsageReportCollection $unreportedUsageReports */
            $unreportedUsageReports = $this->entityManager->findBy(
                UsageReportDefinition::class,
                ['id' => $batchIds],
                $context,
            );

            $usageReports = ImmutableCollection::create($unreportedUsageReports)->map(
                fn(UsageReportEntity $usageReport) => new UsageReport(
                    uuid: $usageReport->getUuid(),
                    orderCount: $usageReport->getOrderCount(),
                    createdAt: $usageReport->getCreatedAt(),
                    inclusiveIntervalStart: $usageReport->getUsageIntervalInclusiveStart(),
                    exclusiveIntervalEnd: $usageReport->getUsageIntervalExclusiveEnd(),
                ),
            );

            try {
                $usageReportRegistrationResponses = $this->usageReportApiClient->registerUsageReports(
                    $usageReports,
                    pickwareShop: $this->usageReportConfiguration->getPickwareShop(),
                    licenseUuid: $this->usageReportConfiguration->getLicenseUuid(),
                );
            } catch (UsageReportApiClientException $exception) {
                $lastRegistrationException = $exception;

                continue;
            }

            $responsesWithIntervalStart = $usageReportRegistrationResponses
                ->filter(fn(UsageReportRegistrationResponse $response) => $response->getInclusiveIntervalStart() !== null);
            $usageReportRegistrationResponsesByIntervalStartDate = array_combine(
                $responsesWithIntervalStart
                    ->map(fn(UsageReportRegistrationResponse $usageReportResponse) => $usageReportResponse
                        ->getInclusiveIntervalStart()
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format('Y-m-d H'))
                    ->asArray(),
                $responsesWithIntervalStart->asArray(),
            );

            $usageReportUpdatePayloadsFromIntervalStart = array_values($unreportedUsageReports
                ->fmap(function(UsageReportEntity $usageReport) use ($usageReportRegistrationResponsesByIntervalStartDate): ?array {
                    $intervalStartDate = DateTimeImmutable::createFromInterface($usageReport->getUsageIntervalInclusiveStart())
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format('Y-m-d H');
                    $usageReportRegistrationResponse = $usageReportRegistrationResponsesByIntervalStartDate[$intervalStartDate] ?? null;
                    if ($usageReportRegistrationResponse === null) {
                        return null;
                    }

                    return [
                        'id' => $usageReport->getId(),
                        'reportedAt' => $usageReportRegistrationResponse->getReportedAt(),
                    ];
                }));

            $responsesWithoutIntervalStart = $usageReportRegistrationResponses
                ->filter(fn(UsageReportRegistrationResponse $response) => $response->getInclusiveIntervalStart() === null);
            $usageReportUpdatePayloadsFromUuid = $responsesWithoutIntervalStart
                ->map(fn(UsageReportRegistrationResponse $response) => [
                    'id' => $response->getUsageReportId(),
                    'reportedAt' => $response->getReportedAt(),
                ])
                ->asArray();

            $usageReportUpdatePayloads = array_merge(
                $usageReportUpdatePayloadsFromIntervalStart,
                $usageReportUpdatePayloadsFromUuid,
            );

            $this->entityManager->update(
                UsageReportDefinition::class,
                $usageReportUpdatePayloads,
                $context,
            );
        }

        if ($lastRegistrationException) {
            $this->usageReportErrorHandler->handleUsageReportApiClientException($lastRegistrationException, $context);
        } else {
            $this->usageReportErrorHandler->handleSuccessfulUsageReportRegistration($context);
        }
    }
}
