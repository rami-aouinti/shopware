<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Command;

use Pickware\UsageReportBundle\UsageReport\UsageReportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pickware-usage-report:trigger-usage-report',
    description: 'Triggers the usage report to send data to Pickware Account.',
)]
class TriggerUsageReportCommand extends Command
{
    public function __construct(
        private readonly ?UsageReportService $usageReportService,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware usage report trigger');

        if ($this->usageReportService === null) {
            $io->error('Usage reporting is not available. Please ensure that your shop is connected to a Pickware Account.');

            return Command::FAILURE;
        }

        $this->usageReportService->reportUsage();

        $io->info('Successfully triggered the usage report.');

        return Command::SUCCESS;
    }
}
