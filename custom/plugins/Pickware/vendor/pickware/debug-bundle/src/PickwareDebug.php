<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DebugBundle;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareDebug
{
    private static self $instance;
    private ContainerInterface $container;

    private function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function initialize(ContainerInterface $container): self
    {
        self::$instance = new self($container);

        return self::getInstance();
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->container->get('monolog.logger.pickware_debug');
    }

    public function getSqlLockLogger(): SqlLockLogger
    {
        return $this->container->get('pickware_debug.sql_lock_logger');
    }
}
