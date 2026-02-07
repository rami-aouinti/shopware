<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Config;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Contracts\Service\ResetInterface;

class Config implements ResetInterface
{
    /**
     * Since there is only one global configuration for the plugin there is only one row in the corresponding database
     * table. This is the ID used for this single row.
     */
    public const CONFIG_ID = '00000000000000000000000000000001';

    private Connection $db;
    private static ?array $rawConfig = null;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function isStockInitialized(): bool
    {
        return (bool) $this->get('stock_initialized');
    }

    public function reset(): void
    {
        self::$rawConfig = null;
    }

    public function setStockInitialized(bool $initialized): void
    {
        $this->set('stock_initialized', $initialized ? 1 : 0);
    }

    public function getDefaultWarehouseId(): string
    {
        $defaultWarehouseId = $this->get('default_warehouse_id');

        return bin2hex($defaultWarehouseId);
    }

    public function setDefaultWarehouseId(string $warehouseId): void
    {
        $this->set('default_warehouse_id', hex2bin($warehouseId));
    }

    public function getDefaultReceivingWarehouseId(): string
    {
        $defaultReceivingWarehouseId = $this->get('default_receiving_warehouse_id');

        return bin2hex($defaultReceivingWarehouseId);
    }

    public function setDefaultReceivingWarehouseId(string $warehouseId): void
    {
        $this->set('default_receiving_warehouse_id', hex2bin($warehouseId));
    }

    private function get(string $fieldName)
    {
        if (self::$rawConfig === null) {
            $dbResult = $this->db->fetchAssociative(
                'SELECT * FROM pickware_erp_config WHERE id = UNHEX(:id)',
                ['id' => self::CONFIG_ID],
            );

            if ($dbResult === false) {
                throw new RuntimeException(sprintf(
                    'There is no row with id=%s in table pickware_erp_config. Please re-install plugin to ' .
                    'fix this problem.',
                    self::CONFIG_ID,
                ));
            }

            self::$rawConfig = $dbResult;
        }

        return self::$rawConfig[$fieldName];
    }

    private function set(string $fieldName, $value): void
    {
        self::$rawConfig = null;
        $this->db->executeStatement(
            'UPDATE pickware_erp_config SET `' . $fieldName . '` = :value',
            [
                'value' => $value,
            ],
        );
    }
}
