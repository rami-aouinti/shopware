<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20230721\AppConfigApiLayer as ApiVersion20230721AppConfigApiLayer;
use Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20240110\AppConfigApiLayer as ApiVersion20240110AppConfigApiLayer;
use Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20240703\AppConfigApiLayer as ApiVersion20240703AppConfigApiLayer;
use Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20240712\AppConfigApiLayer as ApiVersion20240712AppConfigApiLayer;
use Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20241128\AppConfigApiLayer as ApiVersion20241128AppConfigApiLayer;
use Pickware\PickwareWms\Config\Config;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ConfigController
{
    public function __construct(
        private readonly Config $config,
        private readonly CarrierAdapterRegistry $carrierAdapterRegistry,
    ) {}

    #[ApiLayer(ids: [
        ApiVersion20230721AppConfigApiLayer::class,
        ApiVersion20240110AppConfigApiLayer::class,
        ApiVersion20240703AppConfigApiLayer::class,
        ApiVersion20240712AppConfigApiLayer::class,
        ApiVersion20241128AppConfigApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/get-app-config', methods: ['GET'])]
    public function getAppConfig(Context $context): Response
    {
        $config = $this->config->getAppConfig($context);

        return new JsonResponse($config, Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-wms/get-shipping-page-formats', methods: ['GET'])]
    public function getShippingPageFormats(): Response
    {
        $pageFormats = $this->carrierAdapterRegistry->getCarrierPageFormats();

        return new JsonResponse($pageFormats, Response::HTTP_OK);
    }
}
