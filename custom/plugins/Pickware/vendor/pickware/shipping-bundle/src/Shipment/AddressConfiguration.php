<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use JsonSerializable;

class AddressConfiguration implements JsonSerializable
{
    public function __construct(
        private readonly bool $useAlternativeSenderAddress = false,
        private readonly Address $alternativeSenderAddress = new Address(),
        private readonly bool $useImporterOfRecordsAddress = false,
        private readonly Address $importerOfRecordsAddress = new Address(),
    ) {}

    public static function fromArray(array $array): self
    {
        return new self(
            useAlternativeSenderAddress: $array['useAlternativeSenderAddress'] ?? false,
            alternativeSenderAddress: isset($array['alternativeSenderAddress']) ? Address::fromArray($array['alternativeSenderAddress']) : new Address(),
            useImporterOfRecordsAddress: $array['useImporterOfRecordsAddress'] ?? false,
            importerOfRecordsAddress: isset($array['importerOfRecordsAddress']) ? Address::fromArray($array['importerOfRecordsAddress']) : new Address(),
        );
    }

    public function getAlternativeSenderAddress(): Address
    {
        return $this->alternativeSenderAddress;
    }

    public function getUseAlternativeSenderAddress(): bool
    {
        return $this->useAlternativeSenderAddress;
    }

    public function getImporterOfRecordsAddress(): Address
    {
        return $this->importerOfRecordsAddress;
    }

    public function getUseImporterOfRecordsAddress(): bool
    {
        return $this->useImporterOfRecordsAddress;
    }

    public function jsonSerialize(): array
    {
        return [
            'alternativeSenderAddress' => $this->alternativeSenderAddress,
            'useAlternativeSenderAddress' => $this->useAlternativeSenderAddress,
            'importerOfRecordsAddress' => $this->importerOfRecordsAddress,
            'useImporterOfRecordsAddress' => $this->useImporterOfRecordsAddress,
        ];
    }
}
