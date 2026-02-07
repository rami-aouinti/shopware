<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Logging;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class PickingProcessLoggingService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FeatureFlagService $featureFlagService,
        private readonly EntityManager $entityManager,
    ) {}

    public function logPickingProcessStartedOrContinued(string $pickingProcessId, Context $context): void
    {
        $this->logEvent(
            $pickingProcessId,
            'picking_process_started_or_continued',
            $context,
        );
    }

    public function logItemsPicked(string $pickingProcessId, int $numberOfItemsPicked, Context $context): void
    {
        $this->logEvent(
            $pickingProcessId,
            'picking_process_items_picked',
            $context,
            ['numberOfItemsPicked' => $numberOfItemsPicked],
        );
    }

    public function logPickingProcessDeferred(string $pickingProcessId, Context $context): void
    {
        $this->logEvent(
            $pickingProcessId,
            'picking_process_deferred',
            $context,
        );
    }

    public function logPickingProcessCompleted(string $pickingProcessId, Context $context): void
    {
        $this->logEvent(
            $pickingProcessId,
            'picking_process_completed',
            $context,
        );
    }

    public function logPickingProcessPreCollectingCompleted(string $pickingProcessId, Context $context): void
    {
        $this->logEvent(
            $pickingProcessId,
            'picking_process_pre_collecting_completed',
            $context,
        );
    }

    public function logPickingProcessCancelled(string $pickingProcessId, Context $context): void
    {
        $this->logEvent(
            $pickingProcessId,
            'picking_process_cancelled',
            $context,
        );
    }

    private function logEvent(
        string $pickingProcessId,
        string $eventName,
        Context $context,
        array $payload = [],
    ): void {
        if (!$this->featureFlagService->isActive(PickingProcessLoggingProdFeatureFlag::NAME)) {
            return;
        }

        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            ['deliveries'],
        );

        // Add some base information to each picking process log
        $payload = array_merge(
            [
                'userId' => $pickingProcess->getUserId(),
                'orderIds' => array_values($pickingProcess->getDeliveries()->map(
                    fn($delivery) => $delivery->getOrderId(),
                )),
                'pickingMode' => $pickingProcess->getPickingMode(),
            ],
            $payload,
        );

        $this->connection->executeStatement(
            'INSERT INTO `pickware_wms_picking_process_log` (
                `id`,
                `picking_process_id`,
                `created_at`,
                `event_name`,
                `payload`
            ) VALUES (
                :id,
                :pickingProcessId,
                UTC_TIMESTAMP(3),
                :eventName,
                :payload
            )',
            [
                'id' => Uuid::randomBytes(),
                'pickingProcessId' => hex2bin($pickingProcessId),
                'eventName' => $eventName,
                'payload' => Json::stringify($payload),
            ],
        );
    }
}
