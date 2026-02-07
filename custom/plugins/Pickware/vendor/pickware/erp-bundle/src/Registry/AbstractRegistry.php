<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Registry;

use InvalidArgumentException;

abstract class AbstractRegistry
{
    protected array $registeredInstances = [];
    private string $tag;

    protected function __construct(iterable $instances, array $classesToImplement, string $tag)
    {
        $this->tag = $tag;

        $this->throwOnNonMatchingClasses($instances, $classesToImplement);

        foreach ($instances as $instance) {
            $this->registeredInstances[$this->getKey($instance)] = $instance;
        }
    }

    abstract protected function getKey($instance): string;

    protected function getRegisteredInstanceByKey(string $key)
    {
        if (!array_key_exists($key, $this->registeredInstances)) {
            throw new InvalidArgumentException(sprintf(
                'Registered instance with key "%s" not found in registry for tag %s.',
                $key,
                $this->tag,
            ));
        }

        return $this->registeredInstances[$key];
    }

    private function throwOnNonMatchingClasses(iterable $instances, array $classesToImplement): void
    {
        foreach ($instances as $instance) {
            $classesNotImplemented = array_filter(
                $classesToImplement,
                fn($classToImplement) => !($instance instanceof $classToImplement),
            );

            if (count($classesNotImplemented) > 0) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Tagged argument for registry must implement the following ' .
                        'classes: %s. Used tag: %s. Instance passed: %s',
                        implode(', ', $classesNotImplemented),
                        $this->tag,
                        get_class($instance),
                    ),
                );
            }
        }
    }
}
