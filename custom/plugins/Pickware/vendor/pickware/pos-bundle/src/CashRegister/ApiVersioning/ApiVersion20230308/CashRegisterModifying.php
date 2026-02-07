<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister\ApiVersioning\ApiVersion20230308;

use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

trait CashRegisterModifying
{
    private function convertToFiscalizationConfiguration(int|float|bool|array|stdClass &$jsonContent): void
    {
        if (!property_exists($jsonContent, 'fiskalyConfiguration')) {
            return;
        }

        if ($jsonContent->fiskalyConfiguration === null) {
            $jsonContent->fiscalizationConfiguration = null;
        } else {
            $jsonContent->fiscalizationConfiguration = [
                'fiskalyDe' => [
                    'clientUuid' => $jsonContent->fiskalyConfiguration->clientUuid ?? null,
                    'tssUuid' => $jsonContent->fiskalyConfiguration->tssUuid ?? null,
                    'businessPlatformUuid' => $jsonContent->fiskalyConfiguration->businessPlatformUuid ?? null,
                ],
            ];
        }
        unset($jsonContent->fiskalyConfiguration);
    }

    private function convertToFiskalyDeResponse(array &$jsonContent): void
    {
        if (!is_array($jsonContent['fiscalizationConfiguration'] ?? null)) {
            return;
        }

        if (array_key_exists('fiskalyDe', $jsonContent['fiscalizationConfiguration'])) {
            $jsonContent['fiskalyConfiguration'] = [
                // Older app versions need the ID to deserialize the configuration. However it is never used.
                'id' => Uuid::randomHex(),
                'businessPlatformUuid' => $jsonContent['fiscalizationConfiguration']['fiskalyDe']['businessPlatformUuid'] ?? null,
                'clientUuid' => $jsonContent['fiscalizationConfiguration']['fiskalyDe']['clientUuid'] ?? null,
                'tssUuid' => $jsonContent['fiscalizationConfiguration']['fiskalyDe']['tssUuid'] ?? null,
            ];
        }
        unset($jsonContent['fiscalizationConfiguration']);
    }
}
