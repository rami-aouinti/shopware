<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class CreateInitialWarehouseInstallationStep
{
    private Connection $db;
    private EntityManager $entityManager;

    public function __construct(Connection $db, EntityManager $entityManager)
    {
        $this->db = $db;
        $this->entityManager = $entityManager;
    }

    public function install(Context $context): void
    {
        $warehouseExists = $this->db->fetchOne(
            'SELECT COUNT(`id`)
            FROM pickware_erp_warehouse',
        );
        if ($warehouseExists) {
            return;
        }

        /** @var LanguageEntity $systemDefaultLanguage */
        $systemDefaultLanguage = $this->entityManager->findByPrimaryKey(
            LanguageDefinition::class,
            Defaults::LANGUAGE_SYSTEM,
            $context,
            ['locale'],
        );

        $addressId = Uuid::randomHex();
        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_address` (
                `id`,
                `created_at`
            ) VALUES (
                UNHEX(:id),
                UTC_TIMESTAMP(3)
            )',
            ['id' => $addressId],
        );

        $isGermanDefault = mb_stripos($systemDefaultLanguage->getLocale()->getCode(), 'de-') === 0;
        $initialWarehousePayload = [
            'id' => Uuid::randomHex(),
            'code' => $isGermanDefault ? 'HL' : 'MW',
            'name' => $isGermanDefault ? 'Hauptlager' : 'Main warehouse',
            'isStockAvailableForSale' => true,
            'addressId' => $addressId,
        ];
        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_warehouse` (
                `id`,
                `code`,
                `name`,
                `is_stock_available_for_sale`,
                `address_id`,
                `created_at`
            ) VALUES (
                UNHEX(:id),
                :code,
                :name,
                :isStockAvailableForSale,
                UNHEX(:addressId),
                UTC_TIMESTAMP(3)
            )',
            $initialWarehousePayload,
        );
    }
}
