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
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PickwareCreateUserCommand extends Command
{
    private EntityManager $entityManager;
    private string $commandName;
    private string $username;
    private string $userLastName;
    private string $userAclRoleName;
    private Context $context;

    public function __construct(
        EntityManager $entityManager,
        string $commandName,
        string $username,
        string $userLastName,
        string $userAclRoleName,
    ) {
        $this->entityManager = $entityManager;
        $this->commandName = $commandName;
        $this->username = $username;
        $this->userLastName = $userLastName;
        $this->userAclRoleName = $userAclRoleName;
        $this->context = Context::createDefaultContext();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName($this->commandName);
        $this->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware create user');

        $password = $input->getOption('password');
        if ($password == null) {
            $password = Uuid::randomHex();
        }

        /** @var UserEntity $existingUser */
        $existingUser = $this->entityManager->findOneBy(
            UserDefinition::class,
            ['username' => $this->username],
            $this->context,
        );

        /** @var AclRoleEntity $pickwareAclRole */
        $pickwareAclRole = $this->entityManager->getOneBy(
            AclRoleDefinition::class,
            ['name' => $this->userAclRoleName],
            $this->context,
        );

        /** @var LocaleEntity $germanLocale */
        $germanLocale = $this->entityManager->getOneBy(
            LocaleDefinition::class,
            ['code' => 'de-DE'],
            $this->context,
        );

        if ($existingUser) {
            $io->text(sprintf('Update existing user: "%s"', $existingUser->getId()));
        } else {
            $io->text('Creating new user...');
        }

        $this->entityManager->upsert(
            UserDefinition::class,
            [
                [
                    'id' => $existingUser ? $existingUser->getId() : Uuid::randomHex(),
                    'localeId' => $germanLocale->getId(),
                    'username' => $this->username,
                    'firstName' => 'Pickware',
                    'lastName' => $this->userLastName,
                    'password' => password_hash($password, \PASSWORD_BCRYPT),
                    'admin' => false,
                    'email' => $this->username . '@pickware.de',
                    'aclRoles' => [['id' => $pickwareAclRole->getId()]],
                ],
            ],
            $this->context,
        );

        $io->success('Done!');

        return 0;
    }
}
