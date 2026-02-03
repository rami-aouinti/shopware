<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\PickingProfile\DefaultPickingProfileFilterService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class InstallDefaultPickingProfileInstallationStep
{
    private Context $context;

    public function __construct(
        private readonly DefaultPickingProfileFilterService $defaultPickingProfileService,
        private readonly EntityManager $entityManager,
        private readonly Connection $db,
    ) {
        $this->context = Context::createDefaultContext();
    }

    public function writeDefaultPickingProfile(): void
    {
        $pickingProfile = $this->db->fetchOne('SELECT `id` FROM `pickware_wms_picking_profile`');

        if ($pickingProfile !== false) {
            return;
        }

        /** @var ?LanguageEntity $language */
        $language = $this->entityManager->findByPrimaryKey(
            LanguageDefinition::class,
            Defaults::LANGUAGE_SYSTEM,
            $this->context,
            ['locale'],
        );
        $locale = $language?->getLocale()->getCode();

        $this->db->executeQuery(
            'INSERT INTO `pickware_wms_picking_profile` (
                `id`,
                `name`,
                `position`,
                `filter`,
                `created_at`
            ) VALUES (
                :id,
                :name,
                1,
                :filter,
                UTC_TIMESTAMP(3)
            )',
            [
                'id' => Uuid::randomBytes(),
                'name' => ($locale && str_starts_with($locale, 'de')) ? 'Alle Bestellungen' : 'All orders',
                'filter' => Json::stringify($this->defaultPickingProfileService->makeDefaultFilter($this->context)),
            ],
        );
    }
}
