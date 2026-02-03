<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\DemodataGeneration\Generator;

use Pickware\DalBundle\EntityManager;
use Pickware\MobileAppAuthBundle\OAuth\Model\MobileAppCredentialDefinition;
use Pickware\MobileAppAuthBundle\OAuth\Model\MobileAppCredentialEntity;
use Pickware\ShopwareExtensionsBundle\User\UserExtension;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * This generator generates mobile app credentials for each existing user. And it sets the mobile app auth pin to 1234
 * for each user.
 */
class MobileAppCredentialGenerator implements DemodataGeneratorInterface
{
    public const INCLUDE_ADMINS = 'includeAdmins';

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getDefinition(): string
    {
        return MobileAppCredentialDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        /** @var UserCollection $users */
        $users = $this->entityManager->findAll(
            UserDefinition::class,
            $context->getContext(),
            ['pickwareMobileAppCredential'],
        );

        $upsertPayloads = [];
        foreach ($users as $user) {
            if (UserExtension::isAdmin($user) && !($options[self::INCLUDE_ADMINS] ?? false)) {
                continue;
            }

            /** @var ?MobileAppCredentialEntity $existingAppCredential */
            $existingAppCredential = $user->getExtension('pickwareMobileAppCredential');
            if ($existingAppCredential) {
                $upsertPayloads[] = [
                    'id' => $existingAppCredential->getId(),
                    'pin' => '1234',
                ];
            } else {
                $upsertPayloads[] = [
                    'id' => Uuid::randomHex(),
                    'userId' => $user->getId(),
                    'pin' => '1234',
                ];
            }

            if (count($upsertPayloads) >= 50) {
                $this->entityManager->upsert(
                    MobileAppCredentialDefinition::class,
                    $upsertPayloads,
                    $context->getContext(),
                );
                $upsertPayloads = [];
            }
        }

        if (count($upsertPayloads) >= 0) {
            $this->entityManager->upsert(
                MobileAppCredentialDefinition::class,
                $upsertPayloads,
                $context->getContext(),
            );
        }
    }
}
