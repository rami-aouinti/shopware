<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api;

use Pickware\ShippingBundle\Authentication\Credentials;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DpdApiClientConfig extends Credentials
{
    public function __construct(
        string $delisId,
        string $password,
        private readonly bool $shouldUseTestingEndpoint,
        private readonly string $localeCode,
    ) {
        parent::__construct($delisId, $password);
    }

    public function getDelisId(): string
    {
        return $this->getUsername();
    }

    public function shouldUseTestingEndpoint(): bool
    {
        return $this->shouldUseTestingEndpoint;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }
}
