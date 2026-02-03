<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Import;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class BinLocationUpsertService
{
    public function __construct(
        private readonly Connection $db,
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Upserts bin locations for the given list of bin location codes. Since we do not have `id` from the imported csv,
     * we cannot use a real upsert function but instead find the bin location codes that are actually new and insert
     * entities for them manually.
     *
     * @return int number of upserted entities
     *
     * @throws Exception
     */
    public function upsertBinLocations(array $binLocationsPayload, string $warehouseId, Context $context): int
    {
        // Deduplicate bin locations by code
        $binLocationsPayload = array_intersect_key(
            $binLocationsPayload,
            array_unique(array_column($binLocationsPayload, 'code')),
        );
        if (empty($binLocationsPayload)) {
            return 0;
        }

        $existingBinLocationsResult = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(id)) AS id,
                code,
                position
            FROM pickware_erp_bin_location AS bin_location
            WHERE bin_location.code IN (:binLocationCodes)
            AND bin_location.warehouse_id = :warehouseId',
            [
                'binLocationCodes' => array_map(fn($payload) => $payload['code'], $binLocationsPayload),
                'warehouseId' => hex2bin($warehouseId),
            ],
            [
                'binLocationCodes' => ArrayParameterType::STRING,
            ],
        );
        $upsertPayload = [];
        foreach ($binLocationsPayload as $payload) {
            $index = array_search($payload['code'], array_column($existingBinLocationsResult, 'code'), true);
            if ($index === false) {
                $upsertPayload[] = [
                    'id' => Uuid::randomHex(),
                    'warehouseId' => $warehouseId,
                    ...$payload,
                ];

                continue;
            }

            $existingPosition = (int)$existingBinLocationsResult[$index]['position'];
            // A database constraint ensures a position greater than zero. If the result was cast to a zero this means
            // that the position was null in the database.
            $existingPosition = $existingPosition === 0 ? null : $existingPosition;
            if (!array_key_exists('position', $payload) || $existingPosition === $payload['position']) {
                continue;
            }

            $upsertPayload[] = [
                'id' => $existingBinLocationsResult[$index]['id'],
                'warehouseId' => $warehouseId,
                ...$payload,
            ];
        }

        $numberOfUpsertedBinLocations = count($upsertPayload);
        if ($numberOfUpsertedBinLocations > 0) {
            $this->entityManager->upsert(
                BinLocationDefinition::class,
                $upsertPayload,
                $context,
            );
        }

        return $numberOfUpsertedBinLocations;
    }
}
