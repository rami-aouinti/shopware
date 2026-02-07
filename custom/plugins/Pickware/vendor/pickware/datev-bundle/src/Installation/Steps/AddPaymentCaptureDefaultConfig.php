<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Installation\Steps;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AddPaymentCaptureDefaultConfig
{
    public function __construct(private readonly Connection $db) {}

    public function install(): void
    {
        $orderTransactionStates = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`state_machine_state`.`id`)) as stateId,
                `state_machine_state`.`technical_name` as stateTechnicalName
            FROM `state_machine`
            INNER JOIN `state_machine_state` ON `state_machine_id` = `state_machine`.`id`
            WHERE `state_machine`.`technical_name` = :orderTransactionStateMachine
            AND `state_machine_state`.`technical_name` IN (:orderTransactionStates)',
            [
                'orderTransactionStateMachine' => OrderTransactionStates::STATE_MACHINE,
                'orderTransactionStates' => [
                    OrderTransactionStates::STATE_PAID,
                    OrderTransactionStates::STATE_REFUNDED,
                ],
            ],
            ['orderTransactionStates' => ArrayParameterType::STRING],
        );
        $orderTransactionStateIdsIndexedByTechnicalName = array_column(
            $orderTransactionStates,
            'stateId',
            'stateTechnicalName',
        );

        $datevConfigs = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`id`)) as configId,
                `values` as configValues
            FROM `pickware_datev_config`',
        );
        foreach ($datevConfigs as $datevConfig) {
            $configValues = Json::decodeToArray($datevConfig['configValues']);
            $paymentCaptureConfigSet = array_key_exists('paymentCapture', $configValues);
            $paymentCaptureEnabledSet = $paymentCaptureConfigSet && array_key_exists('automaticPaymentCaptureEnabled', $configValues['paymentCapture']);
            if ($paymentCaptureEnabledSet) {
                continue;
            }

            if ($paymentCaptureConfigSet) {
                // Payment capture config is already initialized, only `automaticPaymentCaptureEnabled` is missing
                $hasTransactionStates = count($configValues['paymentCapture']['idsOfOrderTransactionStatesForCaptureTypePayment']) > 0
                    || count($configValues['paymentCapture']['idsOfOrderTransactionStatesForCaptureTypeRefund']) > 0;
                $configValues['paymentCapture'] = [
                    ...$configValues['paymentCapture'],
                    'automaticPaymentCaptureEnabled' => $hasTransactionStates,
                ];
            } else {
                $configValues['paymentCapture'] = [
                    'automaticPaymentCaptureEnabled' => true,
                    'idsOfExcludedPaymentMethods' => [],
                    'idsOfOrderTransactionStatesForCaptureTypePayment' => [
                        $orderTransactionStateIdsIndexedByTechnicalName[OrderTransactionStates::STATE_PAID],
                    ],
                    'idsOfOrderTransactionStatesForCaptureTypeRefund' => [
                        $orderTransactionStateIdsIndexedByTechnicalName[OrderTransactionStates::STATE_REFUNDED],
                    ],
                ];
            }

            $this->db->executeStatement(
                'UPDATE `pickware_datev_config`
                    SET `values` = :configValues
                    WHERE `id` = :configId',
                [
                    'configValues' => Json::stringify($configValues),
                    'configId' => hex2bin($datevConfig['configId']),
                ],
            );
        }
    }
}
