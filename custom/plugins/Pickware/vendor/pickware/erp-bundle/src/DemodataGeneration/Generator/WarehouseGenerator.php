<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Generator;

use Generator;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexHttpException;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * This generator generates demo warehouses.
 */
#[AutoconfigureTag('shopware.demodata_generator')]
class WarehouseGenerator implements DemodataGeneratorInterface
{
    private const DEFAULT_WAREHOUSES = [
        [
            'name' => 'Nachfülllager',
            'code' => 'NL',
            'isStockAvailableForSale' => true,
        ],
        [
            'name' => 'Retourenlager',
            'code' => 'RL',
            'isStockAvailableForSale' => true,
        ],
        [
            'name' => 'Schnelldreher',
            'code' => 'SD',
            'isStockAvailableForSale' => true,
        ],
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getDefinition(): string
    {
        return WarehouseDefinition::class;
    }

    public function generate(int $numberOfWarehouses, DemodataContext $demodataContext, array $options = []): void
    {
        $warehouseGenerator = $this->generateWarehouse($demodataContext);
        for ($i = 0; $i < $numberOfWarehouses; $i++) {
            do {
                $warehouse = $warehouseGenerator->current();
                $warehouseCreated = false;
                try {
                    $this->entityManager->upsert(
                        WarehouseDefinition::class,
                        [$warehouse],
                        $demodataContext->getContext(),
                    );
                    $warehouseCreated = true;
                } catch (UniqueIndexHttpException $e) {
                    // One of the pre-defined warehouse names was already used - just use the next one instead
                    $warehouseGenerator->next();
                }
            } while (!$warehouseCreated);

            $demodataContext->getConsole()->text(sprintf(
                'Created warehouse %d/%d "%s" (%s)',
                $i + 1,
                $numberOfWarehouses,
                $warehouse['name'],
                $warehouse['code'],
            ));

            $warehouseGenerator->next();
        }
    }

    private function generateWarehouse(DemodataContext $demodataContext): Generator
    {
        foreach (self::DEFAULT_WAREHOUSES as $warehouse) {
            yield array_merge(
                $warehouse,
                [
                    'id' => Uuid::randomHex(),
                    'isStockAvailableForSale' => true,
                    'address' => [
                        'street' => $demodataContext->getFaker()->streetName(),
                        'houseNumber' => $demodataContext->getFaker()->buildingNumber(),
                        'zipCode' => $demodataContext->getFaker()->postcode(),
                        'city' => $demodataContext->getFaker()->city(),
                    ],
                ],
            );
        }

        while (true) {
            yield [
                'id' => Uuid::randomHex(),
                'isStockAvailableForSale' => true,
                'code' => Random::getString(3, implode(range('A', 'Z'))),
                'name' => $demodataContext->getFaker()->city() . ' warehouse',
                'address' => [
                    'street' => $demodataContext->getFaker()->streetName(),
                    'houseNumber' => $demodataContext->getFaker()->buildingNumber(),
                    'zipCode' => $demodataContext->getFaker()->postcode(),
                    'city' => $demodataContext->getFaker()->city(),
                ],
            ];
        }
    }
}
