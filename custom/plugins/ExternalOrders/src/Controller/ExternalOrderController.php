<?php declare(strict_types=1);

namespace ExternalOrders\Controller;

use ExternalOrders\Service\ExternalOrderService;
use ExternalOrders\Service\ExternalOrderTestDataService;
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
    public function __construct(
        private readonly ExternalOrderService $externalOrderService,
        private readonly ExternalOrderTestDataService $testDataService,
    ) {
    }

    #[Route(
        path: '/api/_action/external-orders/list',
        name: 'api.admin.external-orders.list',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function list(Request $request, Context $context): Response
    {
        $channel = $request->query->get('channel');
        $search = $request->query->get('search');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        $sort = $request->query->get('sort');
        $order = $request->query->get('order');

        return new JsonResponse(
            $this->externalOrderService->fetchOrders(
                $context,
                $channel ? (string) $channel : null,
                $search ? (string) $search : null,
                $page,
                $limit,
                $sort ? (string) $sort : null,
                $order ? (string) $order : null
            )
        );
    }

    #[Route(
        path: '/api/_action/external-orders/detail/{orderId}',
        name: 'api.admin.external-orders.detail',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function detail(string $orderId, Context $context): Response
    {
        $detail = $this->externalOrderService->fetchOrderDetail($context, $orderId);

        if ($detail === null) {
            return new JsonResponse(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($detail);
    }

    #[Route(
        path: '/api/_action/external-orders/test-data',
        name: 'api.admin.external-orders.test-data',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_POST]
    )]
    public function seedTestData(Context $context): Response
    {
        $inserted = $this->testDataService->seedFakeOrdersOnce($context);

        return new JsonResponse([
            'inserted' => $inserted,
        ]);
    }
}
