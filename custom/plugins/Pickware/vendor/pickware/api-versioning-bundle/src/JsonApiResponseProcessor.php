<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiVersioningBundle;

use JsonException;
use Pickware\PhpStandardLibrary\Json\Json;
use Symfony\Component\HttpFoundation\Response;

class JsonApiResponseProcessor
{
    /**
     * Allows to map the json api content of a response to an array with associatied values by passing all objects of
     * a given type to a callable function. If the content type is not 'application/vnd.api+json' or cannot be decoded,
     * it returns an empty array.
     *
     * @param string $content The content of the response
     * @param string $type The type of entity to be modified
     * @param callable $mapContent The mapping to be applied. Should have one argument of type array and should return an array with 'key' and 'values' keys. I.e. `function (array $jsonContent): array`
     */
    public static function groupValuesOfJsonApiContentForType(mixed $content, string $type, callable $mapContent): array
    {
        $elements = self::getElements($content, $type);

        $keyValueContents = [];
        foreach ($elements as $element) {
            $keyValue = $mapContent($element);
            $keyValueContents[$keyValue['key']] = array_merge(
                $keyValueContents[$keyValue['key']] ?? [],
                $keyValue['values'],
            );
        }

        return $keyValueContents;
    }

    public static function getElement(mixed $content, string $id, string $type): mixed
    {
        $elements = self::getElements($content, $type);

        foreach ($elements as $element) {
            if ($element['id'] === $id) {
                return $element;
            }
        }

        return null;
    }

    public static function parseContent(Response $response): mixed
    {
        if ($response->headers->get('Content-Type') !== 'application/vnd.api+json') {
            return false;
        }

        try {
            return Json::decodeToArray($response->getContent());
        } catch (JsonException $exception) {
            return false;
        }
    }

    public static function getElements(mixed &$content, string $type): array
    {
        $elements = [];
        if (array_key_exists('data', $content)) {
            // Check if data is a list (it can contain a single or multiple objects in JSON API)
            if (array_is_list($content['data'])) {
                foreach ($content['data'] as &$rootObject) {
                    $elements[] = &$rootObject;
                }
                unset($rootObject);
            } else {
                $elements[] = &$content['data'];
            }
        }
        if (array_key_exists('included', $content)) {
            foreach ($content['included'] as &$includedObject) {
                $elements[] = &$includedObject;
            }
            unset($includedObject);
        }

        return array_values(array_filter($elements, fn($value) => $value['type'] === $type));
    }
}
