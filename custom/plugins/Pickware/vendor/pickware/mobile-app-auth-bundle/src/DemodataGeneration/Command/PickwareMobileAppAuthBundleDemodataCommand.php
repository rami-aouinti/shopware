<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\DemodataGeneration\Command;

use Pickware\MobileAppAuthBundle\DemodataGeneration\Generator\MobileAppCredentialGenerator;
use Pickware\MobileAppAuthBundle\OAuth\Model\MobileAppCredentialDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PickwareMobileAppAuthBundleDemodataCommand extends Command
{
    private DemodataService $demodataService;

    public function __construct(DemodataService $demodataService)
    {
        parent::__construct();
        $this->demodataService = $demodataService;
    }

    protected function configure(): void
    {
        $this->setName('pickware-mobile-app:demodata:generate-auth');

        $this->addOption(
            name: 'include-admins',
            shortcut: null,
            mode: InputOption::VALUE_NONE,
            description: 'By default the command creates a PIN only for users that are no admins. With this option a PIN is also generated for admins.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware Mobile App Auth Bundle demo data generator');

        $io->warning('This command will overwrite existing data in your system. It should never be run in production.');
        do {
            $answer = mb_strtolower($io->ask('Do you want to continue? [y/n]', 'y'));
        } while ($answer !== 'y' && $answer !== 'n');
        if ($answer !== 'y') {
            return 0;
        }

        $request = new DemodataRequest();
        $request->add(
            MobileAppCredentialDefinition::class,
            1,
            [MobileAppCredentialGenerator::INCLUDE_ADMINS => $input->getOption('include-admins')],
        );
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
