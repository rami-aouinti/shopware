<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DownloadLabelRequest extends Request
{
    public function __construct(string $bookingId)
    {
        parent::__construct(
            method: 'GET',
            uri: sprintf('printing/v1/labels/%s?printFormat=Portrait1Label', $bookingId),
        );
    }
}
