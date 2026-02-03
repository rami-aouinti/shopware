<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use InvalidArgumentException;

trait ConfigDecoratorTrait
{
    private Config $config;

    public function __construct(Config $config)
    {
        if ($config->getConfigDomain() !== self::CONFIG_DOMAIN) {
            throw new InvalidArgumentException(sprintf(
                'config-domain of passed %s does not match the config domain "%s" and therefore cannot be used as ' .
                'parameter for %s::__construct(). Received config domain: "%s"',
                Config::class,
                self::CONFIG_DOMAIN,
                self::class,
                $config->getConfigDomain(),
            ));
        }
        $this->config = $config;
    }

    public static function fromArray(array $rawConfig): self
    {
        return new self(new Config(self::CONFIG_DOMAIN, $rawConfig));
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function apply(self $other): void
    {
        $this->config->apply($other->config);
    }
}
