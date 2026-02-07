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

use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\ProductPatcher;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command replaces all cover images of all products
 */
#[AsCommand(
    name: 'pickware-erp:demodata:patch-product-images',
    description: 'Patcher for PickwareErp demo data',
)]
class PickwareErpShopwareDemodataPatchProductImagesCommand extends Command
{
    public function __construct(
        private readonly ProductPatcher $productPatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware ERP demo data image patcher');

        $io->warning('This command will overwrite existing data in your system. It should never be run in production.');
        do {
            $answer = mb_strtolower($io->ask('Do you want to continue? [y/n]', 'y'));
        } while ($answer !== 'y' && $answer !== 'n');
        if ($answer !== 'y') {
            return 0;
        }
        $context = Context::createDefaultContext();

        $io->text('Depending on your internet connection, this may take several minutes.');
        $io->text('Patching product images...');
        $this->productPatcher->patchProductImages($context);

        $io->success('Done!');

        return 0;
    }
}
