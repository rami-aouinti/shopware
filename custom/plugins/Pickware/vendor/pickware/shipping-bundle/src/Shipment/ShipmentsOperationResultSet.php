<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use JsonSerializable;

class ShipmentsOperationResultSet implements JsonSerializable
{
    public const RESULT_SUCCESSFUL = 1;
    public const RESULT_PARTLY_SUCCESSFUL = 2;
    public const RESULT_NONE_SUCCESSFUL = 3;
    public const RESULT_NOT_AFFECTED = 4;

    /**
     * @var ShipmentsOperationResult[]
     */
    private array $shipmentsOperationResults = [];

    private array $shipmentsOperationResultsByShipmentId = [];

    public function jsonSerialize(): array
    {
        return [
            'successfullyOrPartlySuccessfullyProcessedShipmentIds' => $this->getSuccessfullyOrPartlySuccessfullyProcessedShipmentIds(),
            'shipmentsOperationResults' => $this->shipmentsOperationResults,
            'areAllOperationResultsSuccessful' => $this->areAllOperationResultsSuccessful(),
            'isAnyOperationResultSuccessful' => $this->isAnyOperationResultSuccessful(),
        ];
    }

    public function addShipmentOperationResult(ShipmentsOperationResult $operation): void
    {
        $this->shipmentsOperationResults[] = $operation;

        foreach ($operation->getProcessedShipmentIds() as $shipmentId) {
            if (!isset($this->shipmentsOperationResultsByShipmentId[$shipmentId])) {
                $this->shipmentsOperationResultsByShipmentId[$shipmentId] = [];
            }
            $this->shipmentsOperationResultsByShipmentId[$shipmentId][] = $operation;
        }
    }

    /**
     * @return ShipmentsOperationResult[]
     */
    public function getShipmentsOperationResults(): array
    {
        return $this->shipmentsOperationResults;
    }

    public function getResultForShipment(string $shipmentId): int
    {
        if (!isset($this->shipmentsOperationResultsByShipmentId[$shipmentId])) {
            return self::RESULT_NOT_AFFECTED;
        }
        /** @var ShipmentsOperationResult[] $operations */
        $operations = $this->shipmentsOperationResultsByShipmentId[$shipmentId];
        $successfulOperations = 0;
        foreach ($operations as $operation) {
            if ($operation->isSuccessful()) {
                $successfulOperations++;
            }
        }

        if (count($operations) === 0) {
            return self::RESULT_NOT_AFFECTED;
        }
        if ($successfulOperations === 0) {
            return self::RESULT_NONE_SUCCESSFUL;
        }
        if ($successfulOperations === count($operations)) {
            return self::RESULT_SUCCESSFUL;
        }

        return self::RESULT_PARTLY_SUCCESSFUL;
    }

    /**
     * @param string[] $shipmentIds
     */
    public function didProcessAllShipments(array $shipmentIds): bool
    {
        foreach ($shipmentIds as $shipmentId) {
            if ($this->getResultForShipment($shipmentId) === self::RESULT_NOT_AFFECTED) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getSuccessfullyOrPartlySuccessfullyProcessedShipmentIds(): array
    {
        $shipmentIds = [];
        foreach ($this->shipmentsOperationResults as $shipmentsOperationResult) {
            if (!$shipmentsOperationResult->isSuccessful()) {
                continue;
            }
            $shipmentIds[] = $shipmentsOperationResult->getProcessedShipmentIds();
        }

        return array_values(array_unique(array_merge([], ...$shipmentIds)));
    }

    /**
     * @return string[]
     */
    public function getFailedProcessedShipmentIds(): array
    {
        $shipmentIds = [];
        foreach ($this->shipmentsOperationResults as $shipmentsOperationResult) {
            if ($shipmentsOperationResult->isSuccessful()) {
                continue;
            }
            $shipmentIds[] = $shipmentsOperationResult->getProcessedShipmentIds();
        }

        return array_unique(array_merge([], ...$shipmentIds));
    }

    public function isAnyOperationResultSuccessful(): bool
    {
        foreach ($this->shipmentsOperationResults as $shipmentsOperationResult) {
            if ($shipmentsOperationResult->isSuccessful()) {
                return true;
            }
        }

        return false;
    }

    public function areAllOperationResultsSuccessful(): bool
    {
        foreach ($this->shipmentsOperationResults as $shipmentsOperationResult) {
            if (!$shipmentsOperationResult->isSuccessful()) {
                return false;
            }
        }

        return true;
    }
}
