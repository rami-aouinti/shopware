<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Event will be dispatched before an ImportExportSchedulerMessage is dispatched to the message queue.
 */
class ImportExportMessageDispatchEvent
{
    /**
     * @var StampInterface[]
     */
    private array $stamps = [];

    public function __construct(private readonly ImportExportSchedulerMessage $message) {}

    /**
     * Add a stamp to the message that will be dispatched to the message queue.
     */
    public function addStamp(StampInterface $stamp): void
    {
        $this->stamps[] = $stamp;
    }

    public function getStamps(): array
    {
        return $this->stamps;
    }

    public function getMessage(): ImportExportSchedulerMessage
    {
        return $this->message;
    }
}
