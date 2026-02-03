<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\PickwareAccount\Command;

use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountService;
use Pickware\LicenseBundle\PickwareAccount\PickwareLicenseLeaseRefreshResult;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pickware-license:refresh-license-lease',
    description: 'Refreshes the license lease for the Pickware license.',
)]
class PickwareLicenseLeaseRefreshCommand extends Command
{
    public function __construct(
        private readonly PickwareAccountService $pickwareAccountService,
        private readonly PluginInstallationRepository $pluginInstallationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware license lease refresh');

        $context = Context::createCLIContext();
        $licenseLeaseRefreshResult = $this->pickwareAccountService->refreshPickwareLicenseLease($context);

        if ($licenseLeaseRefreshResult === PickwareLicenseLeaseRefreshResult::Error) {
            $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);

            $io->error($pluginInstallation->getLatestPickwareLicenseLeaseRefreshError()?->getDetail() ?? 'Unknown error');

            return Command::FAILURE;
        }

        $io->info('Successfully refreshed the license lease.');

        return Command::SUCCESS;
    }
}
