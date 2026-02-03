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
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigDefinition;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\ShippingBundle\Privacy\PrivacyConfiguration;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ShippingMethodConfigImportCommand extends Command
{
    private EntityManager $entityManager;
    private Context $context;

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->context = Context::createDefaultContext();
    }

    protected function configure(): void
    {
        $this->setName('pickware-shipping:shipping-method-config:import');
        $this->addArgument(
            'shipping-method-name',
            InputArgument::REQUIRED,
            'Name of the shipping method (in the default language) to import the config for',
        );
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to a YAML file containing the config to import.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import shipping method config');

        $shippingMethodName = $input->getArgument('shipping-method-name');
        $shippingMethodIds = $this->entityManager->findIdsBy(
            ShippingMethodDefinition::class,
            ['name' => $shippingMethodName],
            $this->context,
        );

        if ($shippingMethodIds === []) {
            $io->error(sprintf('No shipping method found with name "%s"', $shippingMethodName));

            return 1;
        }
        if (count($shippingMethodIds) > 1) {
            $io->warning(sprintf('More than one shipping method with name "%s" found.', $shippingMethodName));
            do {
                $answer = mb_strtolower($io->ask('Do you want to continue? [y/n]', 'y'));
            } while ($answer !== 'y' && $answer !== 'n');
            if ($answer !== 'y') {
                return 0;
            }
        }

        $yamlFilePath = $input->getArgument('file');
        $configYaml = Yaml::parseFile($yamlFilePath);

        $shipmentConfig = $configYaml['shipmentConfig'] ?? [];
        $storefrontConfig = $configYaml['storefrontConfig'] ?? [];
        $returnShipmentConfig = $configYaml['returnShipmentConfig'] ?? [];
        $carrier = $configYaml['carrierTechnicalName'];
        $parcelPackingConfig = ParcelPackingConfiguration::fromArray($configYaml['parcelPackingConfiguration']);
        $privacyConfiguration = PrivacyConfiguration::fromArray($configYaml['privacyConfiguration'] ?? []);
        $payloads = array_map(fn(string $id) => [
            'shippingMethodId' => $id,
            'carrierTechnicalName' => $carrier,
            'shipmentConfig' => $shipmentConfig,
            'storefrontConfig' => $storefrontConfig,
            'returnShipmentConfig' => $returnShipmentConfig,
            'parcelPackingConfiguration' => $parcelPackingConfig,
            'privacyConfiguration' => $privacyConfiguration,
        ], $shippingMethodIds);

        $this->entityManager->runInTransactionWithRetry(function() use ($payloads, $shippingMethodIds): void {
            $this->entityManager->deleteByCriteria(
                ShippingMethodConfigDefinition::class,
                ['shippingMethodId' => $shippingMethodIds],
                $this->context,
            );
            $this->entityManager->upsert(ShippingMethodConfigDefinition::class, $payloads, $this->context);
        });

        $io->success(sprintf(
            'Config from file "%s" has been imported successfully for shipping method(s) "%s".',
            $yamlFilePath,
            $shippingMethodName,
        ));

        return 0;
    }
}
