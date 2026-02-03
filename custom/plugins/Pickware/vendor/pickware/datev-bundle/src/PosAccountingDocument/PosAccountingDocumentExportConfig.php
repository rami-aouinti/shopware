<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument;

use JsonSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosAccountingDocumentExportConfig implements JsonSerializable
{
    public const CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION = 'use-pos-data-model-abstraction';
    public const CONFIG_KEY_ENTITY_ID_COUNT = 'entity-id-count';
    private const REQUIRED_CONFIG_KEYS = [
        self::CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION,
        self::CONFIG_KEY_ENTITY_ID_COUNT,
    ];

    public function __construct(
        public readonly bool $usePosDataModelAbstraction,
        public readonly PosAccountingDocumentEntityIdCount $entityIdCount,
    ) {}

    public static function fromExportConfig(array $exportConfig): self
    {
        return new self(
            $exportConfig[self::CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION],
            PosAccountingDocumentEntityIdCount::fromArray($exportConfig[self::CONFIG_KEY_ENTITY_ID_COUNT]),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            self::CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION => $this->usePosDataModelAbstraction,
            self::CONFIG_KEY_ENTITY_ID_COUNT => $this->entityIdCount,
        ];
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
