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

use Shopware\Core\Profiling\Profiler;

/**
 * @param string $spanName Name of the span. A good suggestion is to use __METHOD__ when tracing a method call
 * @param array $tags An array of tags. Use the enum TracingTag to get the keys.
 */
function trace(
    string $spanName,
    callable $callback,
    array $tags = [],
): void {
    TracingTag::validateTagsArray($tags);

    Profiler::trace($spanName, $callback, 'pickware', $tags);
}
