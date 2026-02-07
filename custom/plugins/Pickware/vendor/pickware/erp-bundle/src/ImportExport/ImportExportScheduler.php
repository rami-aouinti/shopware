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

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[AsMessageHandler]
class ImportExportScheduler
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'messenger.default_bus')]
        private readonly MessageBusInterface $bus,
        private readonly ImportExportSchedulerMessageHandler $importExportSchedulerMessageHandler,
        private readonly ImportExportStateService $importExportStateService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ImportExportSchedulerMessageGenerator $importExportSchedulerMessageGenerator,
    ) {}

    public function scheduleImport(string $importId, Context $context): void
    {
        $this->dispatch(new ImportExportSchedulerMessage($importId, ImportExportSchedulerMessage::STATE_FILE_VALIDATION, $context));
    }

    public function scheduleImportAndSkipReadingFile(string $importId, Context $context): void
    {
        $messages = $this->importExportSchedulerMessageGenerator->createExecuteImportMessagesForImportExport($importId, $context);
        foreach ($messages as $message) {
            $this->dispatch($message);
        }
    }

    public function scheduleExport(string $exportId, Context $context): void
    {
        $this->dispatch(new ImportExportSchedulerMessage(
            $exportId,
            ImportExportSchedulerMessage::STATE_EXECUTE_EXPORT,
            $context,
        ));
    }

    public function __invoke(ImportExportSchedulerMessage $message): void
    {
        $importExportId = $message->getImportExportId();
        /** @var ?ImportExportEntity $importExport */
        $importExport = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importExportId, $message->getContext());
        if (!$importExport) {
            // If the import/export does not exist anymore, we assume that the entity was deleted (by the user) and
            // should not be proceeded anymore. We return silently. See https://github.com/pickware/shopware-plugins/issues/8142
            return;
        }

        try {
            $this->process($message);
        } catch (Throwable $e) {
            // Catch every exception so the message is not retried by the message queue. Failed messages currently
            // cannot be retried because they are not implemented idempotently. Instead we assume that in any
            // case of an exception the import/export has failed hard.
            $errors = new JsonApiErrors([CsvErrorFactory::unknownError($e)]);

            /** @var ?ImportExportEntity $importExport */
            $importExport = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importExportId, $message->getContext());
            if (!$importExport) {
                // The `process()` takes up time, so the user could have deleted the import/export in the meantime. We
                // silently return in this case because there is nothing to update.
                return;
            }

            $this->importExportStateService->fail($importExportId, $errors, $message->getContext());
        }
    }

    private function process(ImportExportSchedulerMessage $message): void
    {
        switch ($message->getState()) {
            case ImportExportSchedulerMessage::STATE_FILE_VALIDATION:
                $nextMessages = $this->importExportSchedulerMessageHandler->handleFileValidationMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_READ_FILE_TO_DATABASE:
                $nextMessages = $this->importExportSchedulerMessageHandler->handleReadFileToDatabaseMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT:
                $nextMessages = $this->importExportSchedulerMessageHandler->handleExecuteImportMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_EXECUTE_EXPORT:
                $nextMessages = $this->importExportSchedulerMessageHandler->handleExecuteExportMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_WRITE_DATABASE_TO_FILE:
                $nextMessages = $this->importExportSchedulerMessageHandler->handleWriteDatabaseToFileMessage($message);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'Invalid state passed to method %s',
                    __METHOD__,
                ));
        }

        foreach ($nextMessages as $nextMessage) {
            $this->dispatch($nextMessage);
        }
    }

    private function dispatch(ImportExportSchedulerMessage $message): void
    {
        $event = new ImportExportMessageDispatchEvent($message);
        $this->eventDispatcher->dispatch($event);
        $stamps = $event->getStamps();

        $this->bus->dispatch($message, $stamps);
    }
}
