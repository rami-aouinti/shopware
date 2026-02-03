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
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;

class ClientRepository implements ClientRepositoryInterface
{
    public const CLIENT_IDENTIFIER = 'administration';

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        if ($clientIdentifier === self::CLIENT_IDENTIFIER) {
            return new ApiClient(self::CLIENT_IDENTIFIER, true, '', false);
        }

        return null;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        return $grantType === PinGrant::PIN_GRANT_TYPE && $clientIdentifier === self::CLIENT_IDENTIFIER;
    }
}
