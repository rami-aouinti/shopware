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

use DOMDocument;
use DOMNode;
use DOMXPath;
use Throwable;

class XmlSanitizer implements HttpSanitizer
{
    public function __construct(
        private readonly string $namespaceName,
        private readonly string $namespaceUri,
        private readonly array $hiddenXmlPaths = [],
        private readonly array $truncatedXmlPaths = [],
    ) {}

    public function filterBody(string $body): string
    {
        $dom = new DOMDocument();
        try {
            $dom->loadXML($body);
        } catch (Throwable $e) {
            return sprintf(
                'Could not parse XML. XML is truncated for security reasons. Length of XML in bytes: %s',
                mb_strlen($body, '8bit'),
            );
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace($this->namespaceName, $this->namespaceUri);

        foreach ($this->hiddenXmlPaths as $hiddenXmlPath) {
            $elements = $xpath->query($hiddenXmlPath);
            /** @var DOMNode $element */
            foreach ($elements as $element) {
                $element->textContent = '*HIDDEN*';
            }
        }

        foreach ($this->truncatedXmlPaths as $truncatedXmlPath) {
            $elements = $xpath->query($truncatedXmlPath);
            /** @var DOMNode $element */
            foreach ($elements as $element) {
                $element->textContent = '*TRUNCATED*';
            }
        }

        return $dom->saveXML();
    }

    public function filterHeader(string $headerName, string $headerValue): string
    {
        return $headerValue;
    }
}
