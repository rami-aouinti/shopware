<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment;

use JsonSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosPaymentExportConfig implements JsonSerializable
{
    public const CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION = 'use-pos-data-model-abstraction';
    public const CONFIG_KEY_ENTITY_ID_COUNT = 'entity-id-count';

    // The entity ID count cannot be required to construct this config since the config needs to exist before creating
    // the ID count. However, we can assert that no export can be started without it by requiring it during validation.
    private const REQUIRED_CONFIG_KEYS = [
        self::CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION,
        self::CONFIG_KEY_ENTITY_ID_COUNT,
    ];

    public function __construct(
        public readonly bool $usePosDataModelAbstraction,
        public readonly ?PosPaymentEntityIdCount $entityIdCount,
    ) {}

    public static function fromExportConfig(array $exportConfig): self
    {
        return new self(
            $exportConfig[self::CONFIG_KEY_USE_POS_DATA_MODEL_ABSTRACTION],
            $exportConfig[self::CONFIG_KEY_ENTITY_ID_COUNT] ? PosPaymentEntityIdCount::fromArray($exportConfig[self::CONFIG_KEY_ENTITY_ID_COUNT]) : null,
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
            if (!isset($config[$requiredConfigKey])) {
                $errors->addError(ImportExportException::createConfigParameterNotSetError($requiredConfigKey));
            }
        }

        return $errors;
    }
}
