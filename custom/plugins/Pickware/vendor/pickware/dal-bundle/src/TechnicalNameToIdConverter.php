<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

class TechnicalNameToIdConverter
{
    private EntityIdResolver $entityIdResolver;

    public function __construct(EntityIdResolver $entityIdResolver)
    {
        $this->entityIdResolver = $entityIdResolver;
    }

    public function convertTechnicalNamesToIdsInOrderPayload(array &$orderPayload): void
    {
        if (isset($orderPayload['stateMachineState']['technicalName'])) {
            $stateId = $this->entityIdResolver->resolveIdForOrderState(
                $orderPayload['stateMachineState']['technicalName'],
            );
            unset($orderPayload['stateMachineState']['technicalName']);
            $orderPayload['stateMachineState']['id'] = $stateId;
        }
        if (isset($orderPayload['stateTechnicalName'])) {
            $stateId = $this->entityIdResolver->resolveIdForOrderState(
                $orderPayload['stateTechnicalName'],
            );
            unset($orderPayload['stateTechnicalName']);
            $orderPayload['stateId'] = $stateId;
        }

        if (isset($orderPayload['deliveries'])) {
            foreach ($orderPayload['deliveries'] as &$delivery) {
                $this->convertTechnicalNamesToIdsInOrderDeliveryPayload($delivery);
            }
        }
        unset($delivery);

        if (isset($orderPayload['transactions'])) {
            foreach ($orderPayload['transactions'] as &$orderTransactionPayload) {
                $this->convertTechnicalNamesToIdsInOrderTransactionPayload($orderTransactionPayload);
            }
        }
        unset($orderTransactionPayload);
    }

    public function convertTechnicalNamesToIdsInOrderDeliveryPayload(array &$orderDeliveryPayload): void
    {
        if (isset($orderDeliveryPayload['stateMachineState']['technicalName'])) {
            $stateId = $this->entityIdResolver->resolveIdForOrderDeliveryState(
                $orderDeliveryPayload['stateMachineState']['technicalName'],
            );
            unset($orderDeliveryPayload['stateMachineState']['technicalName']);
            $orderDeliveryPayload['stateMachineState']['id'] = $stateId;
            if (isset($orderDeliveryPayload['order'])) {
                $this->convertTechnicalNamesToIdsInOrderPayload($orderDeliveryPayload['order']);
            }
        }
        if (isset($orderDeliveryPayload['stateTechnicalName'])) {
            $stateId = $this->entityIdResolver->resolveIdForOrderDeliveryState(
                $orderDeliveryPayload['stateTechnicalName'],
            );
            unset($orderDeliveryPayload['stateTechnicalName']);
            $orderDeliveryPayload['stateId'] = $stateId;
        }
        if (isset($orderDeliveryPayload['order'])) {
            $this->convertTechnicalNamesToIdsInOrderPayload($orderDeliveryPayload['order']);
        }
    }

    public function convertTechnicalNamesToIdsInOrderTransactionPayload(array &$orderTransactionPayload): void
    {
        if (isset($orderTransactionPayload['stateMachineState']['technicalName'])) {
            $stateId = $this->entityIdResolver->resolveIdForOrderTransactionState(
                $orderTransactionPayload['stateMachineState']['technicalName'],
            );
            unset($orderTransactionPayload['stateMachineState']['technicalName']);
            $orderTransactionPayload['stateMachineState']['id'] = $stateId;
        }
        if (isset($orderTransactionPayload['stateTechnicalName'])) {
            $stateId = $this->entityIdResolver->resolveIdForOrderTransactionState(
                $orderTransactionPayload['stateTechnicalName'],
            );
            unset($orderTransactionPayload['stateTechnicalName']);
            $orderTransactionPayload['stateId'] = $stateId;
        }
        if (isset($orderTransactionPayload['order'])) {
            $this->convertTechnicalNamesToIdsInOrderPayload($orderTransactionPayload['order']);
        }
    }
}
