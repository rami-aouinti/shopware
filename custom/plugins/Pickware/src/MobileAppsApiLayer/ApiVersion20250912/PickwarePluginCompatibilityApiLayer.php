<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\Pickware\MobileAppsApiLayer\ApiVersion20250912;

use DateTime;
use JsonException;
use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\Pickware\MobileAppsApiLayer\ApiVersion20250912;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: PluginDefinition::ENTITY_NAME, method: 'search')]
class PickwarePluginCompatibilityApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20250912();
    }

    public function transformRequest(Request $request, Context $context): void {}

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse) || $response->getStatusCode() !== 200) {
            return;
        }

        $this->injectPlugin($response, [
            'name' => 'PickwareWms',
            'version' => '2.41.2',
            'label' => 'Pickware WMS',
        ]);
        $this->injectPlugin($response, [
            'name' => 'PickwarePos',
            'version' => '1.25.2',
            'label' => 'Pickware POS',
        ]);
    }

    /**
     * @param array{name: string, version: string, label: string} $plugin
     */
    private function injectPlugin(JsonResponse $response, array $plugin): void
    {
        try {
            $content = Json::decodeToArray($response->getContent());
        } catch (JsonException $e) {
            // Handle malformed JSON gracefully by not modifying the response
            return;
        }
        if (!is_array($content['data'] ?? null)) {
            return;
        }
        $contentType = $response->headers->get('Content-Type');
        if ($contentType !== null && mb_stripos($contentType, 'application/vnd.api+json') !== false) {
            $this->removeExistingPluginFromJsonApiContent($content, $plugin['name']);
            $this->addPluginToJsonApiContent($content, $plugin);
            $content['meta']['total'] = count($content['data']);
        } else {
            $this->removeExistingPluginFromRegularContent($content, $plugin['name']);
            $this->addPluginToRegularContent($content, $plugin);
            $content['total'] = count($content['data']);
        }
        $response->setContent(Json::stringify($content));
    }

    /**
     * @param array{data: list<array<string, mixed>>} $content
     */
    private function removeExistingPluginFromJsonApiContent(array &$content, string $pluginName): void
    {
        $content['data'] = array_values(array_filter(
            $content['data'],
            fn(array $existingPlugin) => ($existingPlugin['attributes']['name'] ?? null) !== $pluginName,
        ));
    }

    /**
     * @param array{data: array<array<string, mixed>>} $content
     */
    private function removeExistingPluginFromRegularContent(array &$content, string $pluginName): void
    {
        $content['data'] = array_values(array_filter(
            $content['data'],
            fn(array $existingPlugin) => ($existingPlugin['name'] ?? null) !== $pluginName,
        ));
    }

    /**
     * @param array{data: list<array<string, mixed>>} $content
     * @param array{name: string, version: string, label: string} $plugin
     */
    private function addPluginToJsonApiContent(array &$content, array $plugin): void
    {
        $id = Uuid::randomHex();
        $content['data'][] = [
            'id' => $id,
            'type' => 'plugin',
            'attributes' => [
                'name' => $plugin['name'],
                'active' => true,
                'version' => $plugin['version'],
                'installedAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'label' => $plugin['label'],
            ],
            'links' => [
                'self' => '/api/plugin/' . $id,
            ],
            'relationships' => [],
            'meta' => null,
        ];
    }

    /**
     * @param array{data: list<array<string, mixed>>} $content
     * @param array{name: string, version: string, label: string} $plugin
     */
    private function addPluginToRegularContent(array &$content, array $plugin): void
    {
        $id = Uuid::randomHex();
        $content['data'][] = [
            'id' => $id,
            'name' => $plugin['name'],
            'active' => true,
            'version' => $plugin['version'],
            'installedAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'label' => $plugin['label'],
        ];
    }
}
