<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel\DataProvider;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelConfiguration;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelLayoutItemFactory;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelLayouts;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class BinLocationDataProvider extends AbstractBarcodeLabelDataProvider
{
    public const BARCODE_LABEL_TYPE = 'bin_location';
    public const BARCODE_ACTION_CODE = '^3';

    private BarcodeLabelLayoutItemFactory $barcodeLabelLayoutItemFactory;
    private EntityManager $entityManager;

    public function __construct(
        BarcodeLabelLayoutItemFactory $barcodeLabelLayoutItemFactory,
        EntityManager $entityManager,
    ) {
        $this->barcodeLabelLayoutItemFactory = $barcodeLabelLayoutItemFactory;
        $this->entityManager = $entityManager;
    }

    public function getBarcodeLabelType(): string
    {
        return self::BARCODE_LABEL_TYPE;
    }

    public function getSupportedLayouts(): array
    {
        return [BarcodeLabelLayouts::LAYOUT_A];
    }

    public function collectLabelData(BarcodeLabelConfiguration $labelConfiguration, Context $context): array
    {
        $warehouse = $this->getWarehouse($labelConfiguration, $context);
        $binLocations = $this->getBinLocations(
            $warehouse->getId(),
            $labelConfiguration->getDataProviderParamValueByKey('binLocationIds', []),
            $context,
        );

        $data = [];
        foreach ($binLocations as $binLocation) {
            switch ($labelConfiguration->getLayout()) {
                case BarcodeLabelLayouts::LAYOUT_A:
                    $data[] = $this->barcodeLabelLayoutItemFactory->createItemForLayoutA(
                        self::BARCODE_ACTION_CODE . $warehouse->getCode() . '$' . $binLocation->getCode(),
                        $binLocation->getCode(),
                    );
            }
        }

        return $data;
    }

    protected function getWarehouse(BarcodeLabelConfiguration $labelsConfiguration, Context $context): WarehouseEntity
    {
        $warehouseId = $labelsConfiguration->getDataProviderParamValueByKey('warehouseId', null);
        if ($warehouseId === null) {
            throw new InvalidArgumentException('Parameter "warehouseId" missing in barcode label configuration.');
        }

        /** @var WarehouseEntity $warehouse */
        $warehouse = $this->entityManager->getByPrimaryKey(WarehouseDefinition::class, $warehouseId, $context);

        return $warehouse;
    }

    protected function getBinLocations(
        string $warehouseId,
        array $binLocationIds,
        Context $context,
    ): BinLocationCollection {
        if (count($binLocationIds) > 0) {
            $criteria = new Criteria($binLocationIds);
        } else {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('warehouse.id', $warehouseId));
        }
        $criteria->addSorting(new FieldSorting('code', FieldSorting::ASCENDING));

        /** @var BinLocationCollection $binLocations */
        $binLocations = $this->entityManager->findBy(
            BinLocationDefinition::class,
            $criteria,
            $context,
        );

        return $binLocations;
    }
}
