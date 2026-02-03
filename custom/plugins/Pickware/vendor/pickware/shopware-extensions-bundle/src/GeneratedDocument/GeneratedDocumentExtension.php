<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\GeneratedDocument;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

#[Exclude]
class GeneratedDocumentExtension
{
    /**
     * Returns a response for the content of a document.
     */
    public static function createPdfResponse(string $content, string $fileName, string $contentType): Response
    {
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $fileName);
        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
