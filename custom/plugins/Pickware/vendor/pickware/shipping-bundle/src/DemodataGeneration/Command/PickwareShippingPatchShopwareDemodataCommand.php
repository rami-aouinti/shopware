<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\DemodataGeneration\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PickwareShippingPatchShopwareDemodataCommand extends Command
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        parent::__construct();
        $this->db = $db;
    }

    protected function configure(): void
    {
        $this->setName('pickware-shipping:demodata:patch-shopware-demodata');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware Shipping demo data patcher');

        $io->warning('This command will overwrite existing data in your system. It should never be run in production.');
        do {
            $answer = mb_strtolower($io->ask('Do you want to continue? [y/n]', 'y'));
        } while ($answer !== 'y' && $answer !== 'n');
        if ($answer !== 'y') {
            return 0;
        }

        $io->text('✅ Adding a weight to all products.');
        $this->setProductWeight();
        $io->text('✅ Setting Germany as country of all order addresses.');
        $this->setCountriesOfAddressesToGermany();
        $io->text('✅ Adding customs information to all products.');
        $this->addCustomsInformationToProducts();
        $io->text('✅ Adding zip code and house number to all order addresses.');
        $this->addZipCodeAndHouseNumberToOrderAddresses();

        $io->success('Done!');

        return 0;
    }

    private function setProductWeight(): void
    {
        $this->db->executeStatement(
            'UPDATE product SET weight = RAND();',
        );
    }

    private function setCountriesOfAddressesToGermany(): void
    {
        $this->db->executeStatement(
            'UPDATE order_address
            LEFT JOIN country germany on germany.iso = "DE"
            SET country_id = germany.id;',
        );
    }

    private function addCustomsInformationToProducts(): void
    {
        $this->db->executeStatement(
            'UPDATE product_translation
            SET custom_fields = \'{
                "pickware_shipping_customs_information_tariff_number": "10101010",
                "pickware_shipping_customs_information_country_of_origin": "PL"
            }\';',
        );
    }

    private function addZipCodeAndHouseNumberToOrderAddresses(): void
    {
        $this->db->executeStatement(
            'UPDATE order_address
            SET
                street = CONCAT(street, " ", ROUND(RAND() * 200 + 1, 0)),
                zipcode = LPAD(ROUND(RAND() * 10000), 5, "0");',
        );
    }
}
