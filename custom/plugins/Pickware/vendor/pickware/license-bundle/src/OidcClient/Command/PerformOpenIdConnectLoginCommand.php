<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\OidcClient\Command;

use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\LicenseBundle\OidcClient\BusinessPlatformAuthenticationClient;
use Pickware\LicenseBundle\OidcClient\BusinessPlatformAuthenticationException;
use Pickware\LicenseBundle\OidcClient\BusinessPlatformHeadlessOidcFlowClient;
use Pickware\LicenseBundle\OidcClient\BusinessPlatformHeadlessOidcFlowException;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountConnectionResult;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pickware-license:perform-oidc-login',
    description: 'Performs OpenID Connect login with Pickware Account using credentials.',
)]
class PerformOpenIdConnectLoginCommand extends Command
{
    public function __construct(
        private readonly PluginInstallationRepository $pluginInstallationRepository,
        private readonly PickwareAccountService $pickwareAccountService,
        private readonly BusinessPlatformAuthenticationClient $authenticationClient,
        private readonly BusinessPlatformHeadlessOidcFlowClient $headlessOidcFlowClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Pickware Account email')
            ->addArgument('password', InputArgument::REQUIRED, 'Pickware Account password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware Account OIDC Login');

        $context = Context::createCLIContext();
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);

        // Step 1: Authenticate with Business Platform
        $io->text('Logging in to Pickware Account...');
        try {
            $businessPlatformAccessToken = $this->authenticationClient->login(
                email: $input->getArgument('email'),
                password: $input->getArgument('password'),
            );
        } catch (BusinessPlatformAuthenticationException $e) {
            $io->error('Login failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
        $io->text('Login successful.');

        // Step 2: Perform headless OIDC flow
        $io->text('Performing OIDC authorization flow...');
        try {
            $oidcAccessToken = $this->headlessOidcFlowClient->obtainAccessToken(
                $businessPlatformAccessToken,
                $pluginInstallation->getInstallationUuid(),
            );
        } catch (BusinessPlatformHeadlessOidcFlowException $e) {
            $io->error('OIDC flow failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
        $io->text('OIDC access token obtained.');

        // Step 3: Connect Pickware Account
        $io->text('Connecting Pickware Account...');
        $connectionResult = $this->pickwareAccountService->connectToPickwareAccountViaOidcAccessToken(
            $oidcAccessToken,
            $context,
        );
        if ($connectionResult === PickwareAccountConnectionResult::LicenseRefreshError) {
            $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
            $errorDetail = $pluginInstallation->getLatestPickwareLicenseRefreshError()?->getDetail() ?? 'Unknown error';
            $io->error('License refresh failed: ' . $errorDetail);

            return Command::FAILURE;
        }
        if ($connectionResult === PickwareAccountConnectionResult::LicenseLeaseRefreshError) {
            $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
            $errorDetail = $pluginInstallation->getLatestPickwareLicenseLeaseRefreshError()?->getDetail() ?? 'Unknown error';
            $io->error('License lease refresh failed: ' . $errorDetail);

            return Command::FAILURE;
        }
        $io->success('OIDC login completed successfully. Pickware Account is now connected.');

        return Command::SUCCESS;
    }
}
