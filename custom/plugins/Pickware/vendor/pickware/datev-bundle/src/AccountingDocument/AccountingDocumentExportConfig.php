<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument;

use JsonSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentExportConfig implements JsonSerializable
{
    public const CONFIG_KEY_DOCUMENT_TYPES = 'document-types';
    private const REQUIRED_CONFIG_KEYS = [self::CONFIG_KEY_DOCUMENT_TYPES];

    public function __construct(public readonly array $documentTypes) {}

    public static function fromExportConfig(array $exportConfig): self
    {
        return new self($exportConfig[self::CONFIG_KEY_DOCUMENT_TYPES]);
    }

    public function jsonSerialize(): array
    {
        return [self::CONFIG_KEY_DOCUMENT_TYPES => $this->documentTypes];
    }

    public static function validate(array $config): JsonApiErrors
    {
        $errors = JsonApiErrors::noError();
        foreach (self::REQUIRED_CONFIG_KEYS as $requiredConfigKey) {
            if (!array_key_exists($requiredConfigKey, $config)) {
                $errors->addError(ImportExportException::createConfigParameterNotSetError($requiredConfigKey));
            }
        }

        return $errors;
    }
}
