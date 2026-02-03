<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Command;

use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\DemodataGeneration\Generator\StockGenerator;
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command allows to create demo data for Pickware ERP.
 */
#[AsCommand(
    name: 'pickware-erp:demodata:generate',
    description: 'Generates some PickwareErp demo data',
)]
class PickwareErpDemodataCommand extends Command
{
    public function __construct(private readonly DemodataService $demodataService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Warehousing
        $this->addOption(
            'warehouses',
            'w',
            InputOption::VALUE_REQUIRED,
            'Number of warehouses to create',
            1,
        );
        $this->addOption(
            'warehousing-mode',
            'm',
            InputOption::VALUE_REQUIRED,
            sprintf(
                'Warehousing mode: "%s", "%s" or "%s"',
                StockGenerator::WAREHOUSING_MODE_CHAOTIC_LOCATION,
                StockGenerator::WAREHOUSING_MODE_FIXED_LOCATION,
                StockGenerator::WAREHOUSING_MODE_DEMO_LOCATION,
            ),
            StockGenerator::WAREHOUSING_MODE_CHAOTIC_LOCATION,
        );

        // Purchasing
        $this->addOption(
            'suppliers',
            's',
            InputOption::VALUE_REQUIRED,
            'Number of suppliers to create',
            10,
        );

        // Batch management
        $this->addOption(
            'batches',
            'b',
            InputOption::VALUE_REQUIRED,
            'Number of batches to create',
            20,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware ERP demo data generator');
        $request = new DemodataRequest();

        // Warehousing
        $numberOfWarehouses = (int)$input->getOption('warehouses');
        if ($numberOfWarehouses < 0) {
            throw new InvalidArgumentException('Invalid value for parameter "warehouses" supplied.');
        }
        $request->add(WarehouseDefinition::class, $numberOfWarehouses);

        $warehousingMode = $input->getOption('warehousing-mode');
        if (!StockGenerator::isValidWarehousingMode($warehousingMode)) {
            throw new InvalidArgumentException(
                'Value of parameter "warehousing-mode" must be one of "chaotic", "fixed" or "demo".',
            );
        }
        $request->add(
            StockMovementDefinition::class,
            1,
            [
                'warehousing-mode' => $warehousingMode,
            ],
        );

        // Purchasing
        $numberOfSuppliers = (int)$input->getOption('suppliers');
        if ($numberOfSuppliers < 0) {
            throw new InvalidArgumentException('Invalid value for parameter "suppliers" supplied.');
        }
        $request->add(SupplierDefinition::class, $numberOfSuppliers);
        $request->add(ProductSupplierConfigurationDefinition::class, 1);

        // Pickware Product
        $request->add(PickwareProductDefinition::class, 1);

        // Picking Properties
        $request->add(PickingPropertyDefinition::class, 1);

        // Batch management
        $numberOfBatches = (int) $input->getOption('batches');
        if ($numberOfBatches > 0) {
            $request->add(BatchDefinition::class, $numberOfBatches);
        }

        $demoContext = $this->demodataService->generate($request, Context::createDefaultContext(), $io);
        $io->table(
            [
                'Entity',
                'Items',
                'Time',
            ],
            $demoContext->getTimings(),
        );

        return 0;
    }
}
