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

use Doctrine\DBAL\Exception;
use GuzzleHttp\Client;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportBranchStoresCommand extends Command
{
    private EntityManager $entityManager;

    /** @var string[]  */
    private array $httpPrefixes = [
        'https://',
        'http://',
    ];

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('pickware-pos:branch-store:import');
        $this->addArgument('username', InputArgument::REQUIRED, 'Username to BP.');
        $this->addArgument('password', InputArgument::REQUIRED, 'Password to BP.');
        $this->addArgument('shopUrl', InputArgument::OPTIONAL, 'Shop url to import.', getenv('APP_URL'));
        $this->addArgument('pickwareAccountDomain', InputArgument::OPTIONAL, 'Pickware account url.', 'account-staging.pickware.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Branch Store import');

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $baseUrl = 'https://' . str_replace($this->httpPrefixes, '', $input->getArgument('pickwareAccountDomain'));

        $client = new Client();

        // Authenticate and save accessToken
        $response = $client->post($baseUrl . '/api/v3/auth/token/', [
            'json' => [
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $accessToken = Json::decodeToObject($response->getBody()->getContents())->accessToken;

        // Retrieve the first organization
        $response = $client->get(
            $baseUrl . '/api/v3/organization?associations[]=baseData',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ],
        )->getBody()->getContents();
        $organization = Json::decodeToObject($response)[0];
        $organizationUuid = $organization->uuid;

        ## Get shop uuid for input shopUrl
        $response = $client->get(
            $baseUrl . '/api/v3/organization/' . $organizationUuid . '/shop',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ],
        )->getBody()->getContents();
        $shops = Json::decodeToObject($response);

        $shopUrl = str_replace($this->httpPrefixes, '', $input->getArgument('shopUrl'));

        $shopUuid = '';
        foreach ($shops as $shop) {
            if (str_contains($shop->mainShopUrl, $shopUrl)) {
                $shopUuid = $shop->uuid;
                break;
            }
        }

        if (!$shopUuid) {
            $io->error('Shop not found.');

            return 0;
        }

        // Retrieve all branch stores and their cash registers for given shop
        $response = $client->get(
            $baseUrl . '/api/v3/organization/' . $organizationUuid . '/shop/' . $shopUuid . '/branch-store?associations[]=cashRegisters',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ],
        )->getBody()->getContents();
        $branchStores = Json::decodeToObject($response);

        // Create all branch stores and cash registers
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getOneBy(
            SalesChannelDefinition::class,
            ['shortName' => 'POS'],
            Context::createDefaultContext(),
        );

        $payload = [];
        $cashRegisterCount = 0;
        foreach ($branchStores as $branchStore) {
            if ($branchStore->state !== 'active') {
                continue;
            }

            $cashRegisters = [];
            foreach ($branchStore->cashRegisters as $cashRegister) {
                // Remove duplicated deviceUuids
                foreach (array_column($payload, 'cashRegisters', 'id') as $id => $payloadCashRegisters) {
                    $cashRegisterKey = array_search(
                        $cashRegister->deviceUuid,
                        array_column($payloadCashRegisters, 'deviceUuid'),
                    );
                    if ($cashRegisterKey === false) {
                        continue;
                    }

                    if ($payloadCashRegisters[$cashRegisterKey]['updatedAt'] < $cashRegister->updatedAt) {
                        $branchStoreKey = array_search($id, array_column($payload, 'id'));
                        $payload[$branchStoreKey]['cashRegisters'][$cashRegisterKey]['deviceUuid'] = null;
                    } else {
                        $cashRegister->deviceUuid = null;
                    }
                }

                $cashRegisters[] = [
                    'id' => str_replace('-', '', $cashRegister->uuid),
                    'name' => property_exists($cashRegister, 'name') ? $cashRegister->name : 'No name',
                    'deviceUuid' => $cashRegister->deviceUuid,
                    'fiscalizationConfiguration' => [
                        'fiskalyDe' => [
                            'clientUuid' => $cashRegister->fiskalyClientId,
                            'tssUuid' => $cashRegister->fiskalyTssId,
                            'version' => $cashRegister->isV1 ? 'v1' : 'v2',
                            'businessPlatformUuid' => $cashRegister->uuid,
                        ],
                    ],
                    'updatedAt' => $cashRegister->updatedAt,
                ];
            }
            $cashRegisterCount += count($cashRegisters);

            $payload[] = [
                'id' => str_replace('-', '', $branchStore->uuid),
                'name' => $branchStore->fiskalyOrganizationName,
                'fiskalyOrganizationUuid' => $branchStore->fiskalyOrganizationUuid,
                'address' => [
                    'firstName' => $organization->baseData->name,
                    'lastName' => '',
                    'street' => $organization->baseData->addressLine1,
                    'houseNumber' => '',
                    'zipCode' => $organization->baseData->zip,
                    'city' => $organization->baseData->city,
                    'countryIso' => $organization->baseData->isoCountryCode,
                    'vatId' => $organization->baseData->vatId,
                    'phone' => $organization->baseData->phoneNumber,
                    'addressAddition' => $organization->baseData->addressLine2,
                    'comment' => 'Branch Store ' . $branchStore->fiskalyOrganizationName,
                ],
                'salesChannelId' => $salesChannel->getId(), // Uses the default POS saleschannel that we create on install
                'cashRegisters' => $cashRegisters,
            ];
        }

        if (count($payload) > 0) {
            try {
                $this->entityManager->upsert(
                    BranchStoreDefinition::class,
                    $payload,
                    Context::createDefaultContext(),
                );
            } catch (Exception $exception) {
                $io->error('Import failed.');

                return 0;
            }
        }

        $io->success(sprintf('%s Branch stores and %s cash registers imported.', count($payload), $cashRegisterCount));

        return 0;
    }
}
