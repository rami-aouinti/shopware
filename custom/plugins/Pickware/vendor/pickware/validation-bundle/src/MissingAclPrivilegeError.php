<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;

class MissingAclPrivilegeError extends LocalizableJsonApiError
{
    public function __construct(string $privilege)
    {
        parent::__construct([
            'code' => 'PICKWARE_VALIDATION_BUNDLE__MISSING_ACL_PRIVILEGE',
            'title' => [
                'en' => 'Missing ACL privilege',
                'de' => 'Fehlende ACL-Berechtigung',
            ],
            'detail' => [
                'en' => sprintf('The ACL privilege "%s" is required to access this resource.', $privilege),
                'de' => sprintf(
                    'Die ACL-Berechtigung "%s" ist für den Zugriff auf diese Ressource erforderlich.',
                    $privilege,
                ),
            ],
        ]);
    }
}
