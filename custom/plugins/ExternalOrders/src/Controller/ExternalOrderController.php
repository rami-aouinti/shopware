<?php declare(strict_types=1);

namespace ExternalOrders\Controller;

use ExternalOrders\Service\ExternalOrderService;
use ExternalOrders\Service\ExternalOrderSyncService;
use ExternalOrders\Service\ExternalOrderTestDataService;
use ExternalOrders\Service\TopmSan6OrderExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('after-sales')]
class ExternalOrderController extends AbstractController
{
    public function __construct(
        private readonly ExternalOrderService $externalOrderService,
        private readonly ExternalOrderTestDataService $testDataService,
        private readonly ExternalOrderSyncService $syncService,
        private readonly TopmSan6OrderExportService $orderExportService,
        private readonly Connection $connection,
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
        path: '/api/_action/external-orders/detail/{internalOrderId}',
        name: 'api.admin.external-orders.detail',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function detail(string $internalOrderId, Context $context): Response
    {
        $detail = $this->externalOrderService->fetchOrderDetail($context, $internalOrderId);

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
        $this->extendExecutionTimeLimit();

        $inserted = $this->testDataService->seedFakeOrdersOnce($context);

        return new JsonResponse([
            'inserted' => $inserted,
        ]);
    }


    #[Route(
        path: '/api/_action/external-orders/test-data/status',
        name: 'api.admin.external-orders.test-data.status',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function testDataStatus(Context $context): Response
    {
        return new JsonResponse([
            'hasDemoData' => $this->testDataService->hasSeededFakeOrders($context),
        ]);
    }

    #[Route(
        path: '/api/_action/external-orders/test-data/toggle',
        name: 'api.admin.external-orders.test-data.toggle',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_POST]
    )]
    public function toggleTestData(Context $context): Response
    {
        $this->extendExecutionTimeLimit();

        if ($this->testDataService->hasSeededFakeOrders($context)) {
            $removed = $this->testDataService->removeSeededFakeOrders($context);

            return new JsonResponse([
                'action' => 'removed',
                'removed' => $removed,
                'hasDemoData' => false,
            ]);
        }

        $inserted = $this->testDataService->seedFakeOrdersOnce($context);

        return new JsonResponse([
            'action' => 'inserted',
            'inserted' => $inserted,
            'hasDemoData' => true,
        ]);
    }

    private function extendExecutionTimeLimit(): void
    {
        if (!function_exists('set_time_limit')) {
            return;
        }

        @set_time_limit(180);
    }

    #[Route(
        path: '/api/_action/external-orders/mark-test',
        name: 'api.admin.external-orders.mark-test',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_POST]
    )]
    public function markTest(Request $request, Context $context): Response
    {
        $payload = json_decode($request->getContent(), true);
        $internalOrderIds = is_array($payload['orderIds'] ?? null) ? $payload['orderIds'] : [];

        if (is_array($payload['internalOrderIds'] ?? null)) {
            $internalOrderIds = $payload['internalOrderIds'];
        }

        $result = $this->externalOrderService->markOrdersAsTest($context, $internalOrderIds);

        return new JsonResponse($result);
    }

    #[Route(
        path: '/api/_action/external-orders/sync-now',
        name: 'api.admin.external-orders.sync-now',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_POST]
    )]
    public function syncNow(Context $context): Response
    {
        try {
            $this->syncService->syncNewOrders($context);

            return new JsonResponse([
                'success' => true,
                'executedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'executedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(
        path: '/api/_action/external-orders/sync-status',
        name: 'api.admin.external-orders.sync-status',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function syncStatus(): Response
    {
        $row = $this->connection->fetchAssociative(
            'SELECT status, last_execution_time FROM scheduled_task WHERE name = :name ORDER BY created_at DESC LIMIT 1',
            ['name' => 'external_orders.sync']
        );

        if (!is_array($row)) {
            return new JsonResponse([
                'hasTask' => false,
                'status' => null,
                'lastExecutionTime' => null,
                'isSuccess' => null,
            ]);
        }

        $status = isset($row['status']) ? (string) $row['status'] : null;
        $lastExecutionTime = isset($row['last_execution_time']) ? (string) $row['last_execution_time'] : null;

        return new JsonResponse([
            'hasTask' => true,
            'status' => $status,
            'lastExecutionTime' => $lastExecutionTime,
            'isSuccess' => $status !== 'failed' && $lastExecutionTime !== null,
        ]);
    }

    #[Route(
        path: '/api/_action/external-orders/export/{internalOrderId}',
        name: 'api.admin.external-orders.export-order',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_POST]
    )]
    public function exportOrder(string $internalOrderId, Context $context): Response
    {
        try {
            return new JsonResponse($this->orderExportService->exportOrder($internalOrderId, $context));
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(
        path: '/api/_action/external-orders/export-status/{internalOrderId}',
        name: 'api.admin.external-orders.export-status',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function exportStatus(string $internalOrderId): Response
    {
        $status = $this->orderExportService->getLatestExportStatus($internalOrderId);

        return new JsonResponse($status ?? ['status' => 'not_found'], $status === null ? Response::HTTP_NOT_FOUND : Response::HTTP_OK);
    }

    #[Route(
        path: '/topm-export/{token}',
        name: 'api.external-orders.export.file-transfer',
        defaults: [
            'auth_required' => false,
            '_acl' => [],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function serveTopmExportFile(string $token): Response
    {
        $xml = $this->orderExportService->serveSignedExportXml($token);
        if ($xml === null) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        return new HttpResponse($xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
