<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils\Sanitizer;

/**
 * @deprecated Is replaced by the {@link HeaderSanitizer}. Please provide explicit headers to sanitize or use
 * {@link HeaderSanitizer::createForDefaultAuthHeaders} instead. This class will be removed with library version 5.0.0.
 */
class AuthHttpSanitizer extends HeaderSanitizer
{
    public function __construct()
    {
        parent::__construct([
            'AUTHORIZATION',
            'PHP-AUTH-USER',
            'PHP-AUTH-PW',
        ]);
    }
}
