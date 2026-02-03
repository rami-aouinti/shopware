<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api;

use Pickware\ShippingBundle\Authentication\Credentials;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DsvApiClientConfig extends Credentials
{
    public function __construct(
        string $username,
        string $password,
        private readonly bool $shouldUseTestingEndpoint,
    ) {
        parent::__construct($username, $password);
    }

    public function shouldUseTestingEndpoint(): bool
    {
        return $this->shouldUseTestingEndpoint;
    }
}
