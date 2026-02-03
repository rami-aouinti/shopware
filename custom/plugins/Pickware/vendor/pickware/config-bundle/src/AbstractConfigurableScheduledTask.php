<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ConfigBundle;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * A scheduled task whose interval and execution time can be configured via the system config.
 *
 * Since this class cannot have a constructor, the configuration keys for the interval and execution time
 * are configured via implementing the abstract methods.
 */
abstract class AbstractConfigurableScheduledTask extends ScheduledTask
{
    /**
     * @return string|null Name of the system config key where the execution time of this particular scheduled task is
     *     configured. The config value should save the time as a time string HH:MM:SS in UTC. Can be made non-nullable
     *     with the next major version.
     */
    abstract public static function getExecutionTimeConfigKey(): ?string;

    /**
     * @return string|null Name of the system config key where the interval of this particular scheduled task is
     *    configured. The config value should save the interval in seconds as integer
     */
    abstract public static function getExecutionIntervalInSecondsConfigKey(): ?string;
}
