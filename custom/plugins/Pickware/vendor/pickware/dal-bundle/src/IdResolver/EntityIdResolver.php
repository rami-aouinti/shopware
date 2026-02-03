<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\IdResolver;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;

/**
 * Service to resolve system-specific IDs for entities with unique identifiers that are the same on every system.
 * Example: Order State, Country, ...
 *
 * Do not refactor this service to return entities instead of IDs. We don't want entities to be returned by services and
 * also for returning entities, a Context would be required. This service's methods should not have a context parameter.
 */
class EntityIdResolver
{
    public const DEFAULT_RULE_NAME = 'Always valid (Default)';

    public function __construct(
        private readonly Connection $connection,
        /** @deprecated will be non-optional in next major v6.0.0. Is optional for backwards compatibility */
        private readonly ?CachedStateIdService $cachedStateIdService = null,
    ) {}

    /**
     * The country ISO code is not unique among the countries. Select the oldest country that matches instead.
     */
    public function resolveIdForCountry(string $isoCountryCode): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `country`
            WHERE `iso` = :isoCountryCode
            ORDER BY `created_at` ASC
            LIMIT 1',
            ['isoCountryCode' => $isoCountryCode],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No country found for country ISO code "%s".', $isoCountryCode));
        }

        return $id;
    }

    public function resolveIdForOrderState(string $technicalName): string
    {
        if (!$this->cachedStateIdService) {
            return $this->resolveIdForStateMachineState(OrderStates::STATE_MACHINE, $technicalName);
        }

        return $this->cachedStateIdService->getStateIds(OrderStates::STATE_MACHINE, [$technicalName])[0];
    }

    public function resolveIdForOrderDeliveryState(string $technicalName): string
    {
        if (!$this->cachedStateIdService) {
            return $this->resolveIdForStateMachineState(OrderDeliveryStates::STATE_MACHINE, $technicalName);
        }

        return $this->cachedStateIdService->getStateIds(OrderDeliveryStates::STATE_MACHINE, [$technicalName])[0];
    }

    public function resolveIdForOrderTransactionState(string $technicalName): string
    {
        if (!$this->cachedStateIdService) {
            return $this->resolveIdForStateMachineState(OrderTransactionStates::STATE_MACHINE, $technicalName);
        }

        return $this->cachedStateIdService->getStateIds(OrderTransactionStates::STATE_MACHINE, [$technicalName])[0];
    }

    public function resolveIdForStateMachineState(
        string $stateMachineTechnicalName,
        string $stateTechnicalName,
    ): string {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`state_machine_state`.`id`))
            FROM `state_machine_state`
            INNER JOIN `state_machine` ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
            WHERE `state_machine_state`.`technical_name` = :stateTechnicalName
            AND `state_machine`.`technical_name` = :stateMachineTechnicalName
            LIMIT 1',
            [
                'stateTechnicalName' => $stateTechnicalName,
                'stateMachineTechnicalName' => $stateMachineTechnicalName,
            ],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf(
                'No state machine state found for technical name "%s" in state machine "%s".',
                $stateTechnicalName,
                $stateMachineTechnicalName,
            ));
        }

        return $id;
    }

    public function resolveIdForDocumentType(string $technicalName): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `document_type` WHERE `technical_name` = :technicalName LIMIT 1',
            ['technicalName' => $technicalName],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No document type found for technical name "%s".', $technicalName));
        }

        return $id;
    }

    /**
     * The country state short code is not unique among the country states. Select the oldest country state that matches
     * instead.
     */
    public function resolveIdForCountryState(string $isoCountryStateCode): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `country_state`
            WHERE `short_code` = :isoCountryStateCode
            ORDER BY `created_at` ASC
            LIMIT 1',
            ['isoCountryStateCode' => $isoCountryStateCode],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No country state found for country state code "%s".', $isoCountryStateCode));
        }

        return $id;
    }

    public function resolveIdForSalutation(string $salutationKey): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `salutation` WHERE `salutation_key` = :salutationKey LIMIT 1',
            ['salutationKey' => $salutationKey],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No salutation found for salutation key "%s".', $salutationKey));
        }

        return $id;
    }

    public function resolveIdForCurrency(string $isoCurrencyCode): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `currency` WHERE `iso_code` = :isoCurrencyCode LIMIT 1',
            ['isoCurrencyCode' => $isoCurrencyCode],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No currency found for iso code "%s".', $isoCurrencyCode));
        }

        return $id;
    }

    public function resolveIdForLocale(string $code): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `locale` WHERE `code` = :code LIMIT 1',
            ['code' => $code],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No locale found for code "%s".', $code));
        }

        return $id;
    }

    /**
     * There is no single root category in Shopware. We select "a" root category that is the oldest instead.
     */
    public function getRootCategoryId(): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `category`
            WHERE `parent_id` IS NULL
            ORDER BY `created_at` ASC
            LIMIT 1',
        );
        if ($id === false) {
            throw new RuntimeException('No root category found.');
        }

        return $id;
    }

    /**
     * Returns the ID of the (a) rule named 'Always valid (Default)'.
     *
     * It is not guaranteed that this rule exists and also not guaranteed that there is only one rule with this name.
     */
    public function getDefaultRuleId(): ?string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `rule`
            WHERE `name` = :name
            ORDER BY `created_at` ASC
            LIMIT 1',
            ['name' => self::DEFAULT_RULE_NAME],
        );

        if ($id === false) {
            throw new RuntimeException(sprintf('No rule found (default) name "%s".', self::DEFAULT_RULE_NAME));
        }

        return $id;
    }

    public function resolveIdForStateMachine(string $technicalName): string
    {
        /** @var string|false $id */
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `state_machine` WHERE `technical_name` = :technicalName LIMIT 1',
            ['technicalName' => $technicalName],
        );
        if ($id === false) {
            throw new RuntimeException(sprintf('No state machine found for technical name "%s".', $technicalName));
        }

        return $id;
    }
}
