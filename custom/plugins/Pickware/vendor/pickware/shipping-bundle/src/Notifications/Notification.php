<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Notifications;

use JsonSerializable;
use Throwable;

class Notification implements JsonSerializable
{
    private ?Throwable $reason;
    private ?string $code;
    private ?string $message;

    public function __construct(?string $code, ?string $message, ?Throwable $reason = null)
    {
        $this->reason = $reason;
        $this->code = $code;
        $this->message = $message;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'code' => $this->code,
            'message' => $this->message,
        ]);
    }

    public function getReason(): ?Throwable
    {
        return $this->reason;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
