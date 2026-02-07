<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Pickware\DalBundle\EntityManager;
use Pickware\MobileAppAuthBundle\OAuth\Model\MobileAppCredentialDefinition;
use Pickware\MobileAppAuthBundle\OAuth\Model\MobileAppCredentialEntity;
use Shopware\Core\Framework\Api\OAuth\User\User;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

class UserRepository implements UserRepositoryInterface
{
    private EntityManager $entityManager;
    private RequestStack $requestStack;

    public function __construct(EntityManager $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity,
    ): ?UserEntityInterface {
        if ($grantType !== PinGrant::PIN_GRANT_TYPE) {
            return null;
        }
        $context = $this->requestStack->getCurrentRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if ($context === null) {
            throw UserRepositoryException::missingContext();
        }

        /** @var MobileAppCredentialEntity|null $mobileAppCredential */
        $mobileAppCredential = $this->entityManager->findOneBy(
            MobileAppCredentialDefinition::class,
            ['user.username' => $username],
            $context,
            ['user'],
        );
        if (!$mobileAppCredential) {
            return null;
        }

        if (!password_verify($password, $mobileAppCredential->getPin())) {
            return null;
        }

        return new User($mobileAppCredential->getUser()->getId());
    }
}
