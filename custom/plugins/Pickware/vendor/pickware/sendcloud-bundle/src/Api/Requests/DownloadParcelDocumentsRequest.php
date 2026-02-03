<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Api\Requests;

use GuzzleHttp\Psr7\Request;

class DownloadParcelDocumentsRequest extends Request
{
    public function __construct(int $parcelId, string $type)
    {
        parent::__construct(
            'GET',
            sprintf('parcels/%s/documents/%s', $parcelId, $type),
        );
    }
}
