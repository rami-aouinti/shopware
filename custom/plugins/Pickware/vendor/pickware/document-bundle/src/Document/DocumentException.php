<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document;

use Exception;

class DocumentException extends Exception
{
    public static function documentNotFound(string $documentId): self
    {
        return new self(sprintf('The document with ID=%s was not found.', $documentId));
    }
}
