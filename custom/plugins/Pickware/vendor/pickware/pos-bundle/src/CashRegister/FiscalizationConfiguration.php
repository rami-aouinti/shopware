<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister;

use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class FiscalizationConfiguration implements JsonSerializable
{
    private const CASE_FISKALY_DE = 'fiskalyDe';
    private const CASE_FISKALY_AT = 'fiskalyAt';

    private string $case;
    private string $businessPlatformUuid;

    // Fiskaly DE
    private string $clientUuid;
    private string $tssUuid;

    // Fiskaly AT
    private string $cashRegisterUuid;

    private function __construct(string $case)
    {
        $this->case = $case;
    }

    public static function fiskalyDe(string $businessPlatformUuid, string $clientUuid, string $tssUuid): self
    {
        $self = new self(self::CASE_FISKALY_DE);
        $self->businessPlatformUuid = $businessPlatformUuid;
        $self->clientUuid = $clientUuid;
        $self->tssUuid = $tssUuid;

        return $self;
    }

    public static function fiskalyAt(string $businessPlatformUuid, string $cashRegisterUuid): self
    {
        $self = new self(self::CASE_FISKALY_AT);
        $self->businessPlatformUuid = $businessPlatformUuid;
        $self->cashRegisterUuid = $cashRegisterUuid;

        return $self;
    }

    public function jsonSerialize(): array
    {
        switch ($this->case) {
            case self::CASE_FISKALY_DE:
                return [
                    self::CASE_FISKALY_DE => [
                        'clientUuid' => $this->clientUuid,
                        'tssUuid' => $this->tssUuid,
                        'businessPlatformUuid' => $this->businessPlatformUuid,
                    ],
                ];
            case self::CASE_FISKALY_AT:
                return [
                    self::CASE_FISKALY_AT => [
                        'businessPlatformUuid' => $this->businessPlatformUuid,
                        'cashRegisterUuid' => $this->cashRegisterUuid,
                    ],
                ];
            default:
                throw new RuntimeException('Case must be defined');
        }
    }

    public static function fromArray(?array $array): ?self
    {
        if ($array == null) {
            return null;
        }
        if (array_key_exists(self::CASE_FISKALY_DE, $array)) {
            $fiskalyDe = $array[self::CASE_FISKALY_DE];

            return self::fiskalyDe($fiskalyDe['businessPlatformUuid'], $fiskalyDe['clientUuid'], $fiskalyDe['tssUuid']);
        }
        if (array_key_exists(self::CASE_FISKALY_AT, $array)) {
            $fiskalyAt = $array[self::CASE_FISKALY_AT];

            return self::fiskalyAt($fiskalyAt['businessPlatformUuid'], $fiskalyAt['cashRegisterUuid']);
        }

        throw new InvalidArgumentException('No valid json received');
    }

    public function getClientUuid(): ?string
    {
        switch ($this->case) {
            case self::CASE_FISKALY_DE:
                return $this->clientUuid;
            default:
                return null;
        }
    }
}
