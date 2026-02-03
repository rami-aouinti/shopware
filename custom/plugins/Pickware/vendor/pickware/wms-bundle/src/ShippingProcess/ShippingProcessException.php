<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class ShippingProcessException extends Exception implements JsonApiErrorSerializable
{
    private LocalizableJsonApiError $jsonApiError;

    public function __construct(LocalizableJsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function shippingProcessNotFound(string $shippingProcessId): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Shipping process not found',
                    'de' => 'Versandvorgang nicht gefunden',
                ],
                'detail' => [
                    'en' => 'The requested shipping process was not found.',
                    'de' => 'Der angeforderte Versandvorgang wurde nicht gefunden.',
                ],
                'meta' => ['shippingProcessId' => $shippingProcessId],
            ]),
        );
    }

    public static function pickingProcessAlreadyPartOfShippingProcess(
        array $pickingProcessNumbers,
    ): self {
        $isPlural = count($pickingProcessNumbers) > 1;

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Picking process already in shipping',
                    'de' => 'Kommissioniervorgang bereits im Versand',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The picking process' . ($isPlural ? 'es' : '') . ' "%s" ' . ($isPlural ? 'are' : 'is') . ' already part of a shipping process.',
                        implode('", "', $pickingProcessNumbers),
                    ),
                    'de' => sprintf(
                        $isPlural ? 'Die Kommissioniervorgänge "%s" sind bereits Teil eines Versandprozesses.' : 'Der Kommissioniervorgang "%s" ist bereits Teil eines Versandprozesses.',
                        implode('", "', $pickingProcessNumbers),
                    ),
                ],
                'meta' => [
                    'pickingProcessNumbers' => $pickingProcessNumbers,
                ],
            ]),
        );
    }

    public static function pickingProcessNotPicked(
        array $pickingProcessNumbers,
        array $pickingProcessStates,
    ): self {
        $isPlural = count($pickingProcessNumbers) > 1;

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Picking process not completed',
                    'de' => 'Kommissioniervorgang nicht abgeschlossen',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The picking process' . ($isPlural ? 'es' : '') . ' "%s" ' . ($isPlural ? 'are' : 'is') . ' is not in state "picked".',
                        implode('", "', $pickingProcessNumbers),
                    ),
                    'de' => sprintf(
                        $isPlural ? 'Die Kommissioniervorgänge "%s" sind nicht im Status "kommissioniert".' : 'Der Kommissioniervorgang "%s" ist nicht im Status "kommissioniert".',
                        implode('", "', $pickingProcessNumbers),
                    ),
                ],
                'meta' => [
                    'pickingProcessNumbersWithState' => array_combine(
                        $pickingProcessNumbers,
                        $pickingProcessStates,
                    ),
                ],
            ]),
        );
    }

    /**
     * @param string[] $expectedStateNames
     */
    public static function invalidShippingProcessStateForAction(
        string $shippingProcessId,
        string $actualStateName,
        array $expectedStateNames,
    ): self {
        $joinedExpectedStateNames = implode(
            ', ',
            array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
        );

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Invalid shipping process state',
                    'de' => 'Versand in ungültigem Status',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The shipping process is not in one of the expected states %1$s but in the state "%2$s". '
                        . 'The shipping process may have been processed on another device in the meantime.',
                        $joinedExpectedStateNames,
                        $actualStateName,
                    ),
                    'de' => sprintf(
                        'Der Versand befindet sich nicht in einem der erwarteten Status %1$s sondern'
                        . ' im Status "%2$s". Eventuell wurde der Versand in der Zwischenzeit auf '
                        . 'einem anderen Gerät bearbeitet.',
                        $joinedExpectedStateNames,
                        $actualStateName,
                    ),
                ],
                'meta' => [
                    'shippingProcessId' => $shippingProcessId,
                    'actualStateName' => $actualStateName,
                    'expectedStateNames' => $expectedStateNames,
                ],
            ]),
        );
    }

    public static function invalidDevice(
        ?string $shippingProcessDeviceId,
        ?string $shippingProcessDeviceName,
        string $shippingProcessId,
    ): self {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Invalid device',
                    'de' => 'Ungültiges Gerät',
                ],
                'detail' => [
                    'en' => sprintf(
                        'The shipping process is currently processed by another device: %1$s.',
                        $shippingProcessDeviceName ?? '<unknown>',
                    ),
                    'de' => sprintf(
                        'Der Versand wird bereits auf einem anderen Gerät bearbeitet: %1$s.',
                        $shippingProcessDeviceName ?? '<unknown>',
                    ),
                ],
                'meta' => [
                    'deviceId' => $shippingProcessDeviceId,
                    'deviceName' => $shippingProcessDeviceName,
                    'shippingProcessId' => $shippingProcessId,
                ],
            ]),
        );
    }
}
