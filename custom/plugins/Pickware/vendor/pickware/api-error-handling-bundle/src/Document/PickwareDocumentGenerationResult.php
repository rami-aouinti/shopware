<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\Document;

use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;

/**
 * An alternative document generation result class used in the JsonApiErrorFormattingDocumentGeneratorDecorator which
 * allows us to parse our own JsonApiErrors appropriately during JSON serialization. Previously information like `meta`
 * or our custom `code` and `title` were altered/removed. With this implementation, all information is preserved and
 * can sustain existence to outer error handling layers.
 *
 * See https://github.com/pickware/shopware-plugins/issues/3888 and JsonApiErrorFormattingDocumentGeneratorDecorator.
 */
class PickwareDocumentGenerationResult extends DocumentGenerationResult
{
    public static function fromDocumentGenerationResult(DocumentGenerationResult $result): self
    {
        $self = new self();

        foreach ($result->getSuccess()->getElements() as $success) {
            $self->addSuccess($success);
        }

        foreach ($result->getErrors() as $orderId => $error) {
            $self->addError($orderId, $error);
        }

        return $self;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        foreach ($this->getErrors() as $orderId => $error) {
            if ($error instanceof JsonApiErrorSerializable) {
                $data['errors'][$orderId] = [$error->serializeToJsonApiError()->jsonSerialize()];
            } elseif ($error instanceof JsonApiErrorsSerializable) {
                $data['errors'][$orderId] = $error->serializeToJsonApiErrors()->jsonSerialize();
            }
        }

        return $data;
    }
}
