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

class PickwareCustomFieldModificationException extends Exception implements JsonApiErrorSerializable
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
     * @param list<string> $customFieldNames
     */
    public static function pickwareCustomFieldsCannotBeDeleted(array $customFieldNames): self
    {
        natsort($customFieldNames);
        $isSingular = count($customFieldNames) === 1;
        $customFieldNameList = implode(', ', $customFieldNames);

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Cannot delete Pickware custom fields',
                    'de' => 'Pickware-Zusatzfelder können nicht gelöscht werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        ($isSingular ? 'The custom field "%s" is' : 'The custom fields "%s" are') . ' managed by Pickware and cannot be deleted.',
                        $customFieldNameList,
                    ),
                    'de' => sprintf(
                        ($isSingular ? 'Das Zusatzfeld "%s" wird' : 'Die Zusatzfelder "%s" werden') . ' von Pickware verwaltet und können nicht gelöscht werden.',
                        $customFieldNameList,
                    ),
                ],
                'meta' => [
                    'customFieldNames' => $customFieldNames,
                ],
            ]),
        );
    }

    /**
     * @param list<string> $customFieldNames
     */
    public static function pickwareCustomFieldsCannotBeUpdated(array $customFieldNames): self
    {
        natsort($customFieldNames);
        $isSingular = count($customFieldNames) === 1;
        $customFieldNameList = implode(', ', $customFieldNames);

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Cannot update Pickware custom fields',
                    'de' => 'Pickware-Zusatzfelder können nicht bearbeitet werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        ($isSingular ? 'The custom field "%s" is' : 'The custom fields "%s" are') . ' managed by Pickware and cannot be updated.',
                        $customFieldNameList,
                    ),
                    'de' => sprintf(
                        ($isSingular ? 'Das Zusatzfeld "%s" wird' : 'Die Zusatzfelder "%s" werden') . ' von Pickware verwaltet und können nicht bearbeitet werden.',
                        $customFieldNameList,
                    ),
                ],
                'meta' => [
                    'customFieldNames' => $customFieldNames,
                ],
            ]),
        );
    }

    /**
     * @param list<string> $customFieldSetNames
     */
    public static function customFieldsCannotBeCreatedInPickwareCustomFieldSets(array $customFieldSetNames): self
    {
        natsort($customFieldSetNames);
        $isSingular = count($customFieldSetNames) === 1;
        $customFieldSetNameList = implode(', ', $customFieldSetNames);

        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'Cannot create custom fields in Pickware custom field sets',
                    'de' => 'In Pickware-Zusatzfeld-Sets können keine Zusatzfelder erstellt werden',
                ],
                'detail' => [
                    'en' => sprintf(
                        ($isSingular ? 'The custom field set "%s" is' : 'The custom field sets "%s" are') . ' managed by Pickware and custom fields cannot be added to ' . ($isSingular ? 'it' : 'them') . '.',
                        $customFieldSetNameList,
                    ),
                    'de' => sprintf(
                        ($isSingular ? 'Das Zusatzfeld-Set "%s" wird' : 'Die Zusatzfeld-Sets "%s" werden') . ' von Pickware verwaltet und es können keine Zusatzfelder hinzugefügt werden.',
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
