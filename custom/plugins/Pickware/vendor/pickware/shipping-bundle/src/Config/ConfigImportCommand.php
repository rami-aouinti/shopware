<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\Carrier\Model\CarrierDefinition;
use Pickware\ShippingBundle\Carrier\Model\CarrierEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigImportCommand extends Command
{
    private const ARGUMENT_VALUE_COMMON = 'common';

    private ConfigService $shippingConfigService;
    private EntityManager $entityManager;

    public function __construct(ConfigService $shippingConfigService, EntityManager $entityManager)
    {
        parent::__construct();
        $this->shippingConfigService = $shippingConfigService;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('pickware-shipping:config:import');
        $this->addArgument(
            'carrier-technical-name',
            InputArgument::REQUIRED,
            sprintf('Technical name of the carrier (%s: Import common shipping config)', self::ARGUMENT_VALUE_COMMON),
        );
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to a YAML file containing the config to import.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Shipping config import');

        /** @var CarrierEntity $carrier */
        $argument = $input->getArgument('carrier-technical-name');
        if ($argument === self::ARGUMENT_VALUE_COMMON) {
            $configDomain = CommonShippingConfig::CONFIG_DOMAIN;
        } else {
            $carrier = $this->entityManager->findByPrimaryKey(
                CarrierDefinition::class,
                $argument,
                Context::createDefaultContext(),
            );
            if (!$carrier) {
                $io->error(sprintf('Carrier with technical name "%s" not found', $argument));

                return 1;
            }
            $configDomain = $carrier->getConfigDomain();
        }

        $yamlFilePath = $input->getArgument('file');
        $config = Config::readFromYamlFile($configDomain, $yamlFilePath);
        $this->shippingConfigService->saveConfigForSalesChannel($config, null);

        $io->success(sprintf(
            'Config from file "%s" has been imported successfully for config domain "%s".',
            $yamlFilePath,
            $configDomain,
        ));

        return 0;
    }
}
