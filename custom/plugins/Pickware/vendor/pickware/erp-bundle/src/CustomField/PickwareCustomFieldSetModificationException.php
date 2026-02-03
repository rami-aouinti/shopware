<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class PickwareCustomFieldSetModificationException extends Exception implements JsonApiErrorSerializable
{
    public function __construct(private readonly LocalizableJsonApiError $jsonApiError)
    {
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): LocalizableJsonApiError
    {
        return $this->jsonApiError;
    }

    /**
     * @param list<string> $customFieldSetNames
     */
    public static function pickwareCustomFieldSetsCannotBeDeleted(array $customFieldSetNames): self
    {
        natsort($customFieldSetNames);
        $isSingular = count($customFieldSetNames) === 1;
        $customFieldSetNameList = implode(', ', $customFieldSetNames);

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Cannot delete Pickware custom field sets',
                    'de' => 'Pickware-Zusatzfelder können nicht gelöscht werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        ($isSingular ? 'The custom field set "%s" is' : 'The custom field sets "%s" are') . ' managed by Pickware and cannot be deleted.',
                        $customFieldSetNameList,
                    ),
                    'de' => sprintf(
                        ($isSingular ? 'Das Zusatzfeld-Set "%s" wird' : 'Die Zusatzfeld-Sets "%s" werden') . ' von Pickware verwaltet und können nicht gelöscht werden.',
                        $customFieldSetNameList,
                    ),
                ],
                'meta' => [
                    'customFieldSetNames' => $customFieldSetNames,
                ],
            ]),
        );
    }

    /**
     * @param list<string> $customFieldSetNames
     */
    public static function pickwareCustomFieldSetsCannotBeUpdated(array $customFieldSetNames): self
    {
        natsort($customFieldSetNames);
        $isSingular = count($customFieldSetNames) === 1;
        $customFieldSetNameList = implode(', ', $customFieldSetNames);

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Cannot update Pickware custom field sets',
                    'de' => 'Pickware-Zusatzfelder können nicht bearbeitet werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        ($isSingular ? 'The custom field set "%s" is' : 'The custom field sets "%s" are') . ' managed by Pickware and cannot be updated.',
                        $customFieldSetNameList,
                    ),
                    'de' => sprintf(
                        ($isSingular ? 'Das Zusatzfeld-Set "%s" wird' : 'Die Zusatzfeld-Sets "%s" werden') . ' von Pickware verwaltet und können nicht bearbeitet werden.',
                        $customFieldSetNameList,
                    ),
                ],
                'meta' => [
                    'customFieldSetNames' => $customFieldSetNames,
                ],
            ]),
        );
    }
}
