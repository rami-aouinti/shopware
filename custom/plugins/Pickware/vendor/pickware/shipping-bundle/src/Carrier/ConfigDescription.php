<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigDescription
{
    private array $configDescription;

    public static function readFromYamlFile(string $filename): self
    {
        try {
            return new self(Yaml::parseFile($filename) ?: []);
        } catch (ParseException $e) {
            // throw runtime exception because an invalid yaml is ALWAYS a programming error here.
            throw new RuntimeException(sprintf(
                'Yaml file %s could not be parsed: %s',
                $filename,
                $e->getMessage(),
            ), 0, $e);
        }
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public function __construct(array $shipmentConfigDescription)
    {
        $this->configDescription = $shipmentConfigDescription;
    }

    public function getDefaultValues(): array
    {
        return self::getDefaultValuesFromConfigDescription($this->configDescription['elements'] ?? []);
    }

    private static function getDefaultValuesFromConfigDescription(array $configDescription): array
    {
        $defaultValues = [[]];

        foreach ($configDescription as $configElement) {
            if (isset($configElement['elements'])) {
                $defaultValues[] = self::getDefaultValuesFromConfigDescription($configElement['elements']);
            } else {
                $defaultValues[] = [
                    $configElement['name'] => $configElement['default'] ?? null,
                ];
            }
        }

        return array_merge(...$defaultValues);
    }

    public function getOptions(): array
    {
        return $this->configDescription;
    }
}
