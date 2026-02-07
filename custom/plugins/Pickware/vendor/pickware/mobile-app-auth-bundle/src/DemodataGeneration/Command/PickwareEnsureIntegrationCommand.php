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

use Pickware\DalBundle\EntityManager;
use Pickware\MobileAppAuthBundle\Installation\Steps\UpsertMobileAppAclRoleInstallationStep;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// This command ensures that the integration which is hardcoded into our apps for demo mode  exists in the respective
// demo shop.
class PickwareEnsureIntegrationCommand extends Command
{
    private const INTEGRATION_ID = '0192b45ed59574eab3fe36b481102048';

    private readonly Context $context;

    public function __construct(private readonly EntityManager $entityManager)
    {
        parent::__construct();
        $this->context = Context::createDefaultContext();
    }

    protected function configure(): void
    {
        $this->setName('pickware-mobile-app:demodata:ensure-demo-mode-integration');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware ensure app demo integration');

        $this->entityManager->createIfNotExists(
            IntegrationDefinition::class,
            [
                [
                    'id' => self::INTEGRATION_ID,
                    'accessKey' => 'SWIAMK1RYZHAEFE4VMLPCEDHTW',
                    'secretAccessKey' => '$2y$10$LkDQrU0DE75dF0Wwaw2.iOqW55C6VJghgF4WCpTV6mUlnH4AaoNEq',
                    'label' => 'Pickware App',
                    'admin' => false,
                    'aclRoles' => [
                        [
                            'id' => bin2hex(UpsertMobileAppAclRoleInstallationStep::MOBILE_APP_ACL_ROLE_ID_BIN),
                        ],
                    ],
                ],
            ],
            $this->context,
        );

        $io->success('Done!');

        return 0;
    }
}
