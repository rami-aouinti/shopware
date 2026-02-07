<?php declare(strict_types=1);

namespace ExternalOrders\Controller;

use ExternalOrders\Service\ExternalOrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('after-sales')]
class ExternalOrderController extends AbstractController
{
    public function __construct(private readonly ExternalOrderService $externalOrderService)
    {
    }

    #[Route(
        path: '/api/_action/external-orders/list',
        name: 'api.admin.external-orders.list',
        methods: [Request::METHOD_GET],
        defaults: ['_acl' => ['admin']]
    )]
    public function list(Request $request, Context $context): Response
    {
        $channel = $request->query->get('channel');
        $search = $request->query->get('search');

        return new JsonResponse(
            $this->externalOrderService->fetchOrders($context, $channel ? (string) $channel : null, $search ? (string) $search : null)
        );
    }

    #[Route(
        path: '/api/_action/external-orders/detail/{orderId}',
        name: 'api.admin.external-orders.detail',
        methods: [Request::METHOD_GET],
        defaults: ['_acl' => ['admin']]
    )]
    public function detail(string $orderId, Context $context): Response
    {
        $detail = $this->externalOrderService->fetchOrderDetail($context, $orderId);

        if ($detail === null) {
            return new JsonResponse(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($detail);
    }
}
