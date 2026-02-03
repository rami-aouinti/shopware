<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Contracts\EventDispatcher\Event;

enum EntityWriteValidationEventType
{
    case Pre;
    case Post;
    /**
     * @deprecated Will be removed in 6.0.0. Use self::Pre instead
     */
    case pre; // phpcs:ignore VIISON.Enum.EnumCase.NotUpperCamelCase -- Case cant be removed for BC
    /**
     * @deprecated Will be removed in 6.0.0. Use self::Post instead
     */
    case post; // phpcs:ignore VIISON.Enum.EnumCase.NotUpperCamelCase -- Case cant be removed for BC

    public static function fromEvent(Event $event): self
    {
        foreach (self::cases() as $case) {
            if ($case->getShopwareEventClass() === $event::class) {
                return $case;
            }
        }

        throw new InvalidArgumentException('Unsupported Event');
    }

    public function getShopwareEventClass(): string
    {
        return match ($this) {
            self::Pre, self::pre => PreWriteValidationEvent::class,
            self::Post, self::post => PostWriteValidationEvent::class,
        };
    }

    public function getPickwareEventClass(): string
    {
        return match ($this) {
            self::Pre, self::pre => EntityPreWriteValidationEvent::class,
            self::Post, self::post => EntityPostWriteValidationEvent::class,
        };
    }

    public function getEventName(string $entityName): string
    {
        return match ($this) {
            self::Pre, self::pre => sprintf('pickware_dal_bundle.%s.pre_write_validation', $entityName),
            self::Post, self::post => sprintf('pickware_dal_bundle.%s.post_write_validation', $entityName),
        };
    }
}
