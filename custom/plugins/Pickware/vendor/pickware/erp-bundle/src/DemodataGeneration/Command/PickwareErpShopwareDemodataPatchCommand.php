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

use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\ConfigPatcher;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\CountriesPatcher;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\OrderPatcher;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\OrderRecalculationService;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\ProductPatcher;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command patches the existing shopware demo data
 */
#[AsCommand(
    name: 'pickware-erp:demodata:patch-shopware-demodata',
    description: 'Patcher for PickwareErp demo data',
)]
class PickwareErpShopwareDemodataPatchCommand extends Command
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly FeatureFlagRegistry $featureFlagRegistry,
        private readonly CountriesPatcher $countriesPatcher,
        private readonly ProductPatcher $productPatcher,
        private readonly OrderPatcher $orderPatcher,
        private readonly OrderRecalculationService $orderRecalculationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware ERP demo data patcher');

        $io->warning('This command will overwrite existing data in your system. It should never be run in production.');
        do {
            $answer = mb_strtolower($io->ask('Do you want to continue? [y/n]', 'y'));
        } while ($answer !== 'y' && $answer !== 'n');
        if ($answer !== 'y') {
            return 0;
        }
        $context = Context::createDefaultContext();

        $io->text('Patching products...');
        $this->productPatcher->patch($context);

        $io->text('Patching orders...');
        $this->orderPatcher->patch($context);

        $io->text('Patching countries...');
        $this->countriesPatcher->patch($context);

        // Because changing the countries customer tax configuration might affect whether orders are tax-free or not,
        // we need to recalculate all orders for them to have the correct tax status.
        $io->text('Recalculating orders...');
        $this->orderRecalculationService->recalculateAllOrders($context);

        $io->text('Patching ERP Starter Config and SW Feature Flags...');
        $configPatcher = new ConfigPatcher(
            $this->systemConfigService,
            $this->featureFlagRegistry,
        );

        $configPatcher->patch();

        $io->success('Done!');

        return 0;
    }
}
