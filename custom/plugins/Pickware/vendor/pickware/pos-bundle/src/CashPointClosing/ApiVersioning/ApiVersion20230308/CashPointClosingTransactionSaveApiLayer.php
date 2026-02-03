<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\ApiVersioning\ApiVersion20230308;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230308;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CashPointClosingTransactionSaveApiLayer implements ApiLayer
{
    use CashPointClosingTransactionModifying;

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20230308();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            fn(&$jsonContent) => $this->addFiscalizationContext($jsonContent),
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}
}
