<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DebugBundle\Profiling;

use InvalidArgumentException;

enum TracingTag: string
{
    // Please use only the OpenTelemetry Trace Semantic Conventions:
    // https://opentelemetry.io/docs/specs/semconv/general/trace/
    case Stacktrace = 'code.stacktrace';

    public static function validateTagsArray(array $tags): void
    {
        foreach ($tags as $tag => $value) {
            $tag = self::from($tag);
            $tag->validateType($value);
        }
    }

    public function getKey(): string
    {
        return $this->value;
    }

    private function validateType(mixed $value): void
    {
        $expectedType = $this->getType();
        if (gettype($value) !== $expectedType) {
            throw new InvalidArgumentException(sprintf(
                'Expected value of type %s for tag %s, but got %s',
                $expectedType,
                $this->value,
                gettype($value),
            ));
        }
    }

    private function getType(): string
    {
        return match ($this) {
            self::Stacktrace => 'string',
        };
    }
}
