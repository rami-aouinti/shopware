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

use Pickware\ApiVersioningBundle\Attributes\ApiLayer as ApiLayerAttribute;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use ReflectionClass;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiVersioningRequestSubscriber implements EventSubscriberInterface
{
    private const API_LAYERS_REQUEST_ATTRIBUTE = 'pickware_api_layers';

    private array $apiLayers = [];

    public function __construct() {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'processControllerEvent',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST,
            ],
            KernelEvents::RESPONSE => 'processResponseEvent',
        ];
    }

    public function addApiLayer(ApiLayer $apiLayer, string $id): void
    {
        $this->apiLayers[$id] = $apiLayer;
    }

    public function processControllerEvent(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if ($context === null || !($context->getSource() instanceof AdminApiSource)) {
            return;
        }

        // Only try to find applicable api layers once the request passed basic validations to improve performance
        $applicableApiLayers = $this->findApplicableApiLayers($request, $event->getController());
        if (empty($applicableApiLayers)) {
            return;
        }

        // Save the applicable api layers in the request to not having to determine them again when processing
        // the response
        $request->attributes->set(self::API_LAYERS_REQUEST_ATTRIBUTE, $applicableApiLayers);

        foreach ($applicableApiLayers as $apiLayer) {
            $apiLayer->transformRequest($request, $context);
        }
    }

    public function processResponseEvent(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $applicableApiLayers = $request->attributes->get(self::API_LAYERS_REQUEST_ATTRIBUTE);
        if (empty($applicableApiLayers)) {
            return;
        }

        // Apply the layers to the response in reverse order as they were applied to the request (newest first)
        $response = $event->getResponse();
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        foreach (array_reverse($applicableApiLayers) as $apiLayer) {
            $apiLayer->transformResponse($request, $response, $context);
        }
    }

    private function findApplicableApiLayers(Request $request, callable $controllerAction): array
    {
        $requestVersion = ApiVersion::getVersionFromRequest($request);
        if ($requestVersion === null || !is_array($controllerAction)) {
            return [];
        }

        [$controller, $actionMethodName] = $controllerAction;
        if ($controller instanceof ApiController) {
            $applicableApiLayers = $this->findApplicableEntityApiLayers(
                $request->attributes->get('_route'),
                $requestVersion,
            );
        } else {
            $applicableApiLayers = $this->findApplicableControllerActionApiLayers(
                $controller,
                $actionMethodName,
                $requestVersion,
            );
        }

        // Sort all applicable layers by their version, in ascending order (oldest first)
        usort(
            $applicableApiLayers,
            fn(ApiLayer $lhs, ApiLayer $rhs) => $lhs->getVersion()->compareTo($rhs->getVersion()),
        );

        return $applicableApiLayers;
    }

    private function findApplicableEntityApiLayers(string $requestRoute, ApiVersion $requestVersion): array
    {
        return array_values(array_filter(
            $this->apiLayers,
            function(ApiLayer $apiLayer) use ($requestRoute, $requestVersion) {
                if (!$apiLayer->getVersion()->isNewerThan($requestVersion)) {
                    return false;
                }

                foreach ($this->getEntityApiLayers($apiLayer) as $attribute) {
                    $attribute = $attribute->newInstance();

                    $attributeRoute = sprintf(
                        'api.%1$s.%2$s',
                        $attribute->entity,
                        $attribute->method,
                    );

                    if ($attributeRoute === $requestRoute) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    protected function getEntityApiLayers(ApiLayer $apiLayer): array
    {
        return (new ReflectionClass($apiLayer))->getAttributes(EntityApiLayer::class);
    }

    private function findApplicableControllerActionApiLayers(
        $controller,
        string $actionMethodName,
        ApiVersion $requestVersion,
    ): array {
        $controllerReflection = new ReflectionClass($controller);
        $actionMethod = $controllerReflection->getMethod($actionMethodName);
        $apiLayerAttributes = $actionMethod->getAttributes(ApiLayerAttribute::class);

        if (empty($apiLayerAttributes)) {
            return [];
        }

        $apiLayerAttribute = $apiLayerAttributes[0]->newInstance();
        $attributedApiLayerIds = ($apiLayerAttribute !== null) ? $apiLayerAttribute->ids : [];

        return array_values(array_filter(array_map(
            function(string $id) use ($requestVersion) {
                if (isset($this->apiLayers[$id]) && $this->apiLayers[$id]->getVersion()->isNewerThan($requestVersion)) {
                    return $this->apiLayers[$id];
                }

                return null;
            },
            $attributedApiLayerIds,
        )));
    }
}
