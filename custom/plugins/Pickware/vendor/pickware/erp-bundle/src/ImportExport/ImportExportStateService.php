<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ReadingOffset;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\WritingOffset;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class ImportExportStateService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function readFile(string $importExportId, ReadingOffset $stateData, Context $context): void
    {
        /** @var ImportExportEntity $importExport */
        $importExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->entityManager->update(
            ImportExportDefinition::class,
            [
                [
                    'id' => $importExportId,
                    'state' => ImportExportDefinition::STATE_READING_FILE,
                    'stateData' => $stateData->jsonSerialize(),
                ],
            ],
            $context,
        );
        $this->dispatchStateChangeEvent(
            $importExport,
            $importExport->getState(),
            ImportExportDefinition::STATE_READING_FILE,
            $context,
        );
    }

    public function writeFile(
        string $importExportId,
        int $current,
        int $totalCount,
        WritingOffset $writingOffset,
        Context $context,
    ): void {
        /** @var ImportExportEntity $importExport */
        $importExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->entityManager->update(
            ImportExportDefinition::class,
            [
                [
                    'id' => $importExportId,
                    'state' => ImportExportDefinition::STATE_WRITING_FILE,
                    'stateData' => $writingOffset->jsonSerialize(),
                    'currentItem' => $current,
                    'totalNumberOfItems' => $totalCount,
                ],
            ],
            $context,
        );
        $this->dispatchStateChangeEvent(
            $importExport,
            $importExport->getState(),
            ImportExportDefinition::STATE_WRITING_FILE,
            $context,
        );
    }

    public function validate(string $importExportId, Context $context): void
    {
        /** @var ImportExportEntity $importExport */
        $importExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->entityManager->update(
            ImportExportDefinition::class,
            [
                [
                    'id' => $importExportId,
                    'state' => ImportExportDefinition::STATE_VALIDATING_FILE,
                    'startedAt' => new DateTime(),
                ],
            ],
            $context,
        );
        $this->dispatchStateChangeEvent(
            $importExport,
            $importExport->getState(),
            ImportExportDefinition::STATE_VALIDATING_FILE,
            $context,
        );
    }

    public function resetStateData(string $importExportId, Context $context): void
    {
        $this->entityManager->update(ImportExportDefinition::class, [
            [
                'id' => $importExportId,
                'stateData' => [],
            ],
        ], $context);
    }

    /**
     * @param int|null $itemCount The number of items to process. This usually is the number of rows in your CSV file
     *        because every row is processed exactly once. It could be more because there may be some transformation
     *        that should be applied to the rows before. To track the progress of this is transformation you could
     *        "double" or "triple" the $itemCount.
     */
    public function startRun(string $importExportId, int $itemCount, array $stateData, Context $context): void
    {
        /** @var ImportExportEntity $importExport */
        $importExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->entityManager->update(ImportExportDefinition::class, [
            [
                'id' => $importExportId,
                'state' => ImportExportDefinition::STATE_RUNNING,
                'stateData' => $stateData,
                'currentItem' => 0,
                'totalNumberOfItems' => $itemCount,
            ],
        ], $context);
        $this->dispatchStateChangeEvent(
            $importExport,
            $importExport->getState(),
            ImportExportDefinition::STATE_RUNNING,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $stateData
     */
    public function setRunProgress(string $importExportId, int $currentItem, array $stateData, Context $context): void
    {
        $this->entityManager->update(ImportExportDefinition::class, [
            [
                'id' => $importExportId,
                'state' => ImportExportDefinition::STATE_RUNNING,
                'stateData' => $stateData,
                'currentItem' => $currentItem,
            ],
        ], $context);
    }

    /**
     * @param array<string, mixed> $stateData
     */
    public function incrementRunProgress(string $importExportId, int $increment, array $stateData, Context $context): int
    {
        return $this->entityManager->runInTransactionWithRetry(function() use ($increment, $stateData, $context, $importExportId): int {
            $this->entityManager->lockPessimistically(
                ImportExportDefinition::class,
                ['id' => $importExportId],
                $context,
            );

            /**
             * @var ImportExportEntity $importExport
             */
            $importExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);

            $newCurrentItem = $importExport->getCurrentItem() + $increment;

            $this->entityManager->update(ImportExportDefinition::class, [
                [
                    'id' => $importExportId,
                    'state' => ImportExportDefinition::STATE_RUNNING,
                    'stateData' => $stateData,
                    'currentItem' => $newCurrentItem,
                ],
            ], $context);

            return $newCurrentItem;
        });
    }

    public function finish(string $importExportId, Context $context): void
    {
        $this->resetStateData($importExportId, $context);

        /** @var ImportExportEntity $importExport */
        $importExport = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $importExportId,
            $context,
        );

        $state = ImportExportDefinition::STATE_COMPLETED;

        // Check whether the import or export has any import elements with errors (legacy)
        $importElementWithErrors = $this->entityManager->findOneBy(
            ImportExportElementDefinition::class,
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('importExportId', $importExportId),
                    new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('errors', null)]),
                )
                ->setLimit(1),
            $context,
        );

        // Check whether the import or export has any log entries with the log level 'error'
        $logEntry = $this->entityManager->findOneBy(
            ImportExportLogEntryDefinition::class,
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('importExportId', $importExportId),
                    new EqualsFilter('logLevel', ImportExportLogLevel::Error->value),
                )
                ->setLimit(1),
            $context,
        );
        if ($importElementWithErrors !== null || $logEntry !== null) {
            $state = ImportExportDefinition::STATE_COMPLETED_WITH_ERRORS;
        }

        $this->entityManager->update(ImportExportDefinition::class, [
            [
                'id' => $importExportId,
                'state' => $state,
                'stateData' => [],
                'currentItem' => $importExport->getTotalNumberOfItems(),
                'completedAt' => new DateTime(),
            ],
        ], $context);

        if ($this->featureFlagService->isActive(DeleteImportExportElementsFeatureFlag::NAME)) {
            $this->entityManager->deleteByCriteria(
                ImportExportElementDefinition::class,
                ['importExportId' => $importExportId],
                $context,
            );
        }

        $this->dispatchStateChangeEvent(
            $importExport,
            $importExport->getState(),
            $state,
            $context,
        );
    }

    public function fail(string $importExportId, JsonApiErrors $errors, Context $context): void
    {
        /** @var ImportExportEntity $importExport */
        $importExport = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $importExportId,
            $context,
        );
        $this->entityManager->update(ImportExportDefinition::class, [
            [
                'id' => $importExportId,
                'state' => ImportExportDefinition::STATE_FAILED,
                'importExportLogEntries' => array_map(
                    fn(JsonApiError $error) => [
                        'importExportId' => $importExportId,
                        'logLevel' => ImportExportLogLevel::Error,
                        'message' => ImportExportLogEntryMessage::fromJsonApiError($error),
                    ],
                    $errors->getErrors(),
                ),
                'completedAt' => $importExport->getStartedAt() ? new DateTime() : null,
            ],
        ], $context);

        if ($this->featureFlagService->isActive(DeleteImportExportElementsFeatureFlag::NAME)) {
            $this->entityManager->deleteByCriteria(
                ImportExportElementDefinition::class,
                ['importExportId' => $importExportId],
                $context,
            );
        }

        $this->dispatchStateChangeEvent(
            $importExport,
            $importExport->getState(),
            ImportExportDefinition::STATE_FAILED,
            $context,
        );
    }

    public function failImportExportElement(string $importExportElementId, JsonApiErrors $errors, Context $context): void
    {
        /** @var ImportExportElementEntity $importExportElement */
        $importExportElement = $this->entityManager->getByPrimaryKey(ImportExportElementDefinition::class, $importExportElementId, $context);

        $this->entityManager->create(
            ImportExportLogEntryDefinition::class,
            array_map(
                fn(JsonApiError $error) => [
                    'importExportId' => $importExportElement->getImportExportId(),
                    'rowNumber' => $importExportElement->getRowNumber(),
                    'logLevel' => ImportExportLogLevel::Error,
                    'message' => ImportExportLogEntryMessage::fromJsonApiError($error),
                ],
                $errors->getErrors(),
            ),
            $context,
        );
    }

    private function dispatchStateChangeEvent(
        ImportExportEntity $importExport,
        string $fromState,
        string $toState,
        Context $context,
    ): void {
        $this->eventDispatcher->dispatch(
            new ImportExportStateChangeEvent(
                $importExport->getId(),
                $importExport->getType(),
                $importExport->getProfileTechnicalName(),
                $fromState,
                $toState,
                $context,
            ),
            ImportExportStateChangeEvent::EVENT_NAME,
        );
    }
}
