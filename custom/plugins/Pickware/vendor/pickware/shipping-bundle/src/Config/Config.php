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

use ArrayAccess;
use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use ReturnTypeWillChange;
use Symfony\Component\Yaml\Yaml;

class Config implements IteratorAggregate, ArrayAccess
{
    private string $configDomain;
    private array $rawConfig;

    public function __construct(string $configDomain, array $rawConfig = [])
    {
        $this->configDomain = $configDomain;
        $this->rawConfig = $rawConfig;
    }

    public function getRawConfig(): array
    {
        return $this->rawConfig;
    }

    public function getConfigDomain(): string
    {
        return $this->configDomain;
    }

    public static function readFromYamlFile(string $configDomain, string $yamlFilePath): self
    {
        return new self(
            $configDomain,
            Yaml::parseFile($yamlFilePath),
        );
    }

    public function apply(self $other): void
    {
        if ($other->configDomain !== $this->configDomain) {
            throw new InvalidArgumentException(sprintf(
                'Cannot apply a config from an %s object with different $configDomain. ' .
                'Tried to apply domain "%s" to "%s".',
                self::class,
                $other->configDomain,
                $this->configDomain,
            ));
        }

        $this->rawConfig = array_merge(
            $this->rawConfig,
            $other->rawConfig,
        );
    }

    /**
     * @return string[]
     */
    public function getMultilineConfigValueAsArray(string $key): array
    {
        $configValue = trim($this->rawConfig[$key] ?? '');
        $lines = explode("\n", $configValue);
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines));
    }

    public function assertNotEmpty(string $fieldName): void
    {
        if (empty($this->rawConfig[$fieldName])) {
            throw ConfigException::missingConfigurationField($this->configDomain, $fieldName);
        }
    }

    public function assertMatchRegex(string $fieldName, string $pattern): void
    {
        if (!preg_match($pattern, $this[$fieldName])) {
            throw ConfigException::invalidFormattedField($this->configDomain, $fieldName);
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->rawConfig[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->rawConfig[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->rawConfig[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->rawConfig[$offset]);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->rawConfig);
    }
}
