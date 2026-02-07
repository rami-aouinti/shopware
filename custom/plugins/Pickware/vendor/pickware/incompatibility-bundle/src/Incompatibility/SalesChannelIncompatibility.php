<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\IncompatibilityBundle\Incompatibility;

class SalesChannelIncompatibility implements Incompatibility
{
    public function __construct(
        private readonly string $salesChannelTypeId,
        private readonly ?array $translatedWarnings = null,
    ) {}

    public function getSalesChannelTypeId(): string
    {
        return $this->salesChannelTypeId;
    }

    public function getVerifierServiceName(): string
    {
        return SalesChannelIncompatibilityVerifier::class;
    }

    public function getTranslatedWarnings(): array
    {
        return $this->translatedWarnings ?? [
            'en-GB' => sprintf(
                'Found an incompatible sales channel (Sales Channel Type ID %s).',
                $this->salesChannelTypeId,
            ),
            'de-DE' => sprintf(
                'Inkompatibler Verkaufskanal gefunden (Sales Channel Type ID %s).',
                $this->salesChannelTypeId,
            ),
        ];
    }
}
