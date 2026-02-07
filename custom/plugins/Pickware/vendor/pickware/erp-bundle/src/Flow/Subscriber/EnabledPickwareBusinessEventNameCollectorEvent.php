<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Flow\Subscriber;

use Symfony\Contracts\EventDispatcher\Event;

class EnabledPickwareBusinessEventNameCollectorEvent extends Event
{
    /**
     * @param string[] $enabledBusinessEvents
     */
    public function __construct(
        private array $enabledBusinessEvents = [],
    ) {}

    public function addEnabledBusinessEventName(string $eventName): void
    {
        $this->enabledBusinessEvents[] = $eventName;
    }

    /**
     * @param string[] $enabledBusinessEvents
     */
    public function addValidPrefixes(array $enabledBusinessEvents): void
    {
        $this->enabledBusinessEvents = array_merge($this->enabledBusinessEvents, $enabledBusinessEvents);
    }

    /**
     * @return string[]
     */
    public function getEnabledBusinessEvents(): array
    {
        return $this->enabledBusinessEvents;
    }
}
