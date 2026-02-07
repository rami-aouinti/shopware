<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EnsureDemoBranchStoreCommand extends Command
{
    private readonly Context $context;

    public function __construct(private readonly EntityManager $entityManager)
    {
        parent::__construct();
        $this->context = Context::createDefaultContext();
    }

    protected function configure(): void
    {
        $this->setName('pickware-pos:demodata:ensure-demo-mode-branch-store');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Ensure app demo data branch store');

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getOneBy(
            SalesChannelDefinition::class,
            ['shortName' => 'POS'],
            $this->context,
        );

        $this->entityManager->createIfNotExists(
            BranchStoreDefinition::class,
            [
                [
                    'id' => '1b18efd5dd0148268544795c4093b9dd',
                    'name' => 'Pickware Shop',
                    'salesChannelId' => $salesChannel->getId(),
                    'address' => [
                        'street' => 'Goebelstraße',
                        'houseNumber' => '21',
                        'zipCode' => '64293',
                        'city' => 'Darmstadt',
                        'countryIso' => 'AC',
                        'state' => 'Hessen',
                    ],
                ],
            ],
            $this->context,
        );

        $io->success('Done!');

        return 0;
    }
}
