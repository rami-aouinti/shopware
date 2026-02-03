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

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Api\OAuth\ScopeRepository as ShopwareScopeRepository;

class ScopeRepository extends ShopwareScopeRepository
{
    public function __construct(iterable $scopes, Connection $connection)
    {
        parent::__construct($scopes, $connection);
    }

    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
        $authCodeId = null,
    ): array {
        $finalizedScopes = parent::finalizeScopes(
            $scopes,
            $grantType,
            $clientEntity,
            $userIdentifier,
            $authCodeId,
        );

        if ($grantType === PinGrant::PIN_GRANT_TYPE && !$this->hasScope($finalizedScopes, new WriteScope())) {
            $finalizedScopes[] = new WriteScope();
        }

        return $finalizedScopes;
    }

    private function hasScope(array $haystack, ScopeEntityInterface $needle): bool
    {
        /** @var ScopeEntityInterface $scope */
        foreach ($haystack as $scope) {
            if ($scope->getIdentifier() === $needle->getIdentifier()) {
                return true;
            }
        }

        return false;
    }
}
