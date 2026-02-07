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

class CreateDefaultPickingPropertyInstallationStep
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $db,
    ) {}

    public function install(Context $context): void
    {
        $existingPickingProperty = $this->db->fetchOne('SELECT `id` FROM `pickware_erp_picking_property`');
        if ($existingPickingProperty !== false) {
            return;
        }

        $localeCode = $this->entityManager
            ->findByPrimaryKey(
                LanguageDefinition::class,
                Defaults::LANGUAGE_SYSTEM,
                $context,
                ['locale'],
            )
            ?->getLocale()
            ->getCode();

        $this->db->executeQuery(
            'INSERT INTO `pickware_erp_picking_property` (
                `id`,
                `name`,
                `created_at`
            ) VALUES (
                :id,
                :name,
                UTC_TIMESTAMP(3)
            )',
            [
                'id' => Uuid::randomBytes(),
                'name' => ($localeCode && str_starts_with($localeCode, 'de')) ? 'Seriennummer' : 'Serial number',
            ],
        );
    }
}
