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

class PluginIncompatibility implements Incompatibility
{
    /**
     * @param string $conflictingPlugin technical name of the plugin that is incompatible with your bundle
     * @param string|null $minVersion minimum version with, since then (including), the plugin is incompatible. null, if there is no lower version limit to the plugin incompatibility
     * @param string|null $maxVersion maximum version with, up until then (including), the plugin is incompatible. null, if there is no upper version limit to the plugin incompatibility
     */
    public function __construct(
        private readonly string $conflictingPlugin,
        private readonly ?string $minVersion = null,
        private readonly ?string $maxVersion = null,
        private readonly ?array $translatedWarnings = null,
    ) {}

    public function getConflictingPlugin(): string
    {
        return $this->conflictingPlugin;
    }

    public function getMinVersion(): ?string
    {
        return $this->minVersion;
    }

    public function getMaxVersion(): ?string
    {
        return $this->maxVersion;
    }

    public function getVerifierServiceName(): string
    {
        return PluginIncompatibilityVerifier::class;
    }

    public function getTranslatedWarnings(): array
    {
        return $this->translatedWarnings ?? [
            'en-GB' => sprintf(
                'Found an incompatible plugin (Plugin name %s).',
                $this->conflictingPlugin,
            ),
            'de-DE' => sprintf(
                'Inkompatibles Plugin gefunden (Plugin Name %s).',
                $this->conflictingPlugin,
            ),
        ];
    }
}
