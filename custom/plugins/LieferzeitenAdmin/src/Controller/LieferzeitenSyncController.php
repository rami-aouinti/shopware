<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Controller;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use LieferzeitenAdmin\Service\LieferzeitenPositionWriteService;
use LieferzeitenAdmin\Service\LieferzeitenTaskService;
use LieferzeitenAdmin\Service\WriteEndpointConflictException;
use LieferzeitenAdmin\Service\LieferzeitenStatisticsService;
use LieferzeitenAdmin\Service\DemoDataSeederService;
use LieferzeitenAdmin\Service\Tracking\TrackingHistoryService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('after-sales')]
class LieferzeitenSyncController extends AbstractController
{
    public function __construct(
        private readonly LieferzeitenImportService $importService,
        private readonly TrackingHistoryService $trackingHistoryService,
        private readonly LieferzeitenOrderOverviewService $orderOverviewService,
        private readonly LieferzeitenPositionWriteService $positionWriteService,
        private readonly LieferzeitenTaskService $taskService,
        private readonly LieferzeitenStatisticsService $statisticsService,
        private readonly DemoDataSeederService $demoDataSeederService,
        private readonly AuditLogService $auditLogService,
    ) {
    }


    #[Route(
        path: '/api/_action/lieferzeiten/tasks',
        name: 'api.admin.lieferzeiten.tasks.list',
        defaults: ['_acl' => ['lieferzeiten.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function listTasks(Request $request, Context $context): JsonResponse
    {
        $data = $this->taskService->listTasks(
            $context,
            $request->query->get('status') ? (string) $request->query->get('status') : null,
            $request->query->get('assignee') ? (string) $request->query->get('assignee') : null,
            (int) $request->query->get('page', 1),
            (int) $request->query->get('limit', 25),
        );

        return new JsonResponse($data);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/tasks/{taskId}/assign',
        name: 'api.admin.lieferzeiten.tasks.assign',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function assignTask(string $taskId, Request $request, Context $context): JsonResponse
    {
        if (!Uuid::isValid($taskId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid task id'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->toArray();
        $assignee = trim((string) ($payload['assignee'] ?? ''));
        if ($assignee === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'assignee is required'], Response::HTTP_BAD_REQUEST);
        }

        $this->taskService->assignTask($taskId, $assignee, $context);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/tasks/{taskId}/close',
        name: 'api.admin.lieferzeiten.tasks.close',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function closeTask(string $taskId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($taskId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid task id'], Response::HTTP_BAD_REQUEST);
        }

        $this->taskService->closeTask($taskId, $context);

        return new JsonResponse(['status' => 'ok']);
    }


    #[Route(
        path: '/api/_action/lieferzeiten/tasks/{taskId}/reopen',
        name: 'api.admin.lieferzeiten.tasks.reopen',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function reopenTask(string $taskId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($taskId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid task id'], Response::HTTP_BAD_REQUEST);
        }

        $this->taskService->reopenTask($taskId, $context);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/tasks/{taskId}/cancel',
        name: 'api.admin.lieferzeiten.tasks.cancel',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function cancelTask(string $taskId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($taskId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid task id'], Response::HTTP_BAD_REQUEST);
        }

        $this->taskService->cancelTask($taskId, $context);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/orders',
        name: 'api.admin.lieferzeiten.orders',
        defaults: ['_acl' => ['lieferzeiten.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function orders(Request $request, Context $context): JsonResponse
    {
        $payload = $this->orderOverviewService->listOrders(
            (int) $request->query->get('page', 1),
            (int) $request->query->get('limit', 25),
            $request->query->get('sort') ? (string) $request->query->get('sort') : null,
            $request->query->get('order') ? (string) $request->query->get('order') : null,
            [
                'bestellnummer' => $request->query->get('bestellnummer'),
                'san6' => $request->query->get('san6'),
                'orderDateFrom' => $request->query->get('orderDateFrom'),
                'orderDateTo' => $request->query->get('orderDateTo'),
                'shippingDateFrom' => $request->query->get('shippingDateFrom'),
                'shippingDateTo' => $request->query->get('shippingDateTo'),
                'deliveryDateFrom' => $request->query->get('deliveryDateFrom'),
                'deliveryDateTo' => $request->query->get('deliveryDateTo'),
                'user' => $request->query->get('user'),
                'sendenummer' => $request->query->get('sendenummer'),
                'status' => $request->query->get('status'),
                'shippingAssignmentType' => $request->query->get('shippingAssignmentType'),
                'businessDateFrom' => $request->query->get('businessDateFrom'),
                'businessDateTo' => $request->query->get('businessDateTo'),
                'businessDateEndFrom' => $request->query->get('businessDateEndFrom'),
                'businessDateEndTo' => $request->query->get('businessDateEndTo'),
                'paymentDateFrom' => $request->query->get('paymentDateFrom'),
                'paymentDateTo' => $request->query->get('paymentDateTo'),
                'calculatedDeliveryDateFrom' => $request->query->get('calculatedDeliveryDateFrom'),
                'calculatedDeliveryDateTo' => $request->query->get('calculatedDeliveryDateTo'),
                'lieferterminLieferantFrom' => $request->query->get('lieferterminLieferantFrom'),
                'lieferterminLieferantTo' => $request->query->get('lieferterminLieferantTo'),
                'neuerLieferterminFrom' => $request->query->get('neuerLieferterminFrom'),
                'neuerLieferterminTo' => $request->query->get('neuerLieferterminTo'),
                'domain' => $request->query->get('domain'),
            ],
        );

        $this->auditLogService->log('orders_viewed', 'lieferzeiten_orders', null, $context, [
            'page' => (int) $request->query->get('page', 1),
            'limit' => (int) $request->query->get('limit', 25),
        ], 'shopware');

        return new JsonResponse($payload);
    }


    #[Route(
        path: '/api/_action/lieferzeiten/statistics',
        name: 'api.admin.lieferzeiten.statistics',
        defaults: ['_acl' => ['lieferzeiten.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function statistics(Request $request, Context $context): JsonResponse
    {
        $period = (int) $request->query->get('period', 30);
        $domain = $request->query->get('domain') ? (string) $request->query->get('domain') : null;
        $channel = $request->query->get('channel') ? (string) $request->query->get('channel') : null;

        $payload = $this->statisticsService->getStatistics($period, $domain, $channel);

        $this->auditLogService->log('statistics_viewed', 'lieferzeiten_statistics', null, $context, [
            'period' => $period,
            'domain' => $domain,
            'channel' => $channel,
        ], 'shopware');

        return new JsonResponse($payload);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/sync',
        name: 'api.admin.lieferzeiten.sync',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function syncNow(Context $context): JsonResponse
    {
        $this->importService->sync($context, 'on_demand');
        $this->auditLogService->log('sync_started', 'lieferzeiten_sync', null, $context, [], 'shopware');

        return new JsonResponse(['status' => 'ok']);
    }


    #[Route(
        path: '/api/_action/lieferzeiten/demo-data',
        name: 'api.admin.lieferzeiten.demo_data',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function seedDemoData(Request $request, Context $context): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $reset = is_array($payload) ? (bool) ($payload['reset'] ?? false) : false;

        $result = $this->demoDataSeederService->seed($context, $reset);

        $this->auditLogService->log('demo_data_seeded', 'lieferzeiten_demo_data', null, $context, [
            'reset' => $reset,
            'created' => $result['created'] ?? [],
            'deleted' => $result['deleted'] ?? [],
        ], 'shopware');

        return new JsonResponse($result);
    }



    #[Route(
        path: '/api/_action/lieferzeiten/demo-data/status',
        name: 'api.admin.lieferzeiten.demo_data.status',
        defaults: ['_acl' => ['lieferzeiten.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function demoDataStatus(): JsonResponse
    {
        return new JsonResponse([
            'hasDemoData' => $this->demoDataSeederService->hasDemoData(),
        ]);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/demo-data/toggle',
        name: 'api.admin.lieferzeiten.demo_data.toggle',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function toggleDemoData(Context $context): JsonResponse
    {
        if ($this->demoDataSeederService->hasDemoData()) {
            $result = $this->demoDataSeederService->removeDemoData($context);

            $this->auditLogService->log('demo_data_removed', 'lieferzeiten_demo_data', null, $context, [
                'deleted' => $result['deleted'] ?? [],
            ], 'shopware');

            return new JsonResponse(array_merge($result, [
                'action' => 'removed',
                'hasDemoData' => false,
            ]));
        }

        $result = $this->demoDataSeederService->seed($context, true);

        $this->auditLogService->log('demo_data_seeded', 'lieferzeiten_demo_data', null, $context, [
            'reset' => true,
            'created' => $result['created'] ?? [],
            'deleted' => $result['deleted'] ?? [],
        ], 'shopware');

        return new JsonResponse(array_merge($result, [
            'action' => 'inserted',
            'hasDemoData' => true,
        ]));
    }

    #[Route(
        path: '/api/_action/lieferzeiten/tracking/{carrier}/{trackingNumber}',
        name: 'api.admin.lieferzeiten.tracking-history',
        defaults: ['_acl' => ['lieferzeiten.viewer']],
        methods: [Request::METHOD_GET]
    )]
    public function trackingHistory(string $carrier, string $trackingNumber, Context $context): JsonResponse
    {
        $response = $this->trackingHistoryService->fetchHistory($carrier, $trackingNumber);
        $this->auditLogService->log('tracking_viewed', 'tracking', $trackingNumber, $context, ['carrier' => $carrier], 'shopware');

        if (($response['ok'] ?? false) === true) {
            return new JsonResponse($response);
        }

        $httpCode = match ($response['errorCode'] ?? null) {
            'rate_limit' => Response::HTTP_TOO_MANY_REQUESTS,
            'invalid_tracking_number' => Response::HTTP_BAD_REQUEST,
            'carrier_not_supported' => Response::HTTP_BAD_REQUEST,
            'timeout' => Response::HTTP_GATEWAY_TIMEOUT,
            default => Response::HTTP_BAD_GATEWAY,
        };

        return new JsonResponse($response, $httpCode);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/position/{positionId}/liefertermin-lieferant',
        name: 'api.admin.lieferzeiten.position.liefertermin_lieferant',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function updateLieferterminLieferant(string $positionId, Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validatePositionId($positionId);
        if ($validationError !== null) {
            return $validationError;
        }

        $payload = $request->toArray();
        $expectedUpdatedAt = trim((string) ($payload['updatedAt'] ?? ''));
        if ($expectedUpdatedAt === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'updatedAt is required for optimistic concurrency control'], Response::HTTP_BAD_REQUEST);
        }

        $range = $this->extractDateRange($payload);
        if ($range === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'from/to are required date values'], Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->validateRange($range['from'], $range['to'], 1, 14);
        if ($validationError !== null) {
            return $validationError;
        }

        if (!$this->positionWriteService->hasNeuerLieferterminHistoryForPosition($positionId)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Supplier delivery date can only be updated when an initial new delivery date (1-4 days) already exists for this line',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->positionWriteService->updateLieferterminLieferant($positionId, $range['from'], $range['to'], $expectedUpdatedAt, $context);
        } catch (WriteEndpointConflictException $e) {
            $this->auditLogService->log('liefertermin_lieferant_update_conflict', 'lieferzeiten_position', $positionId, $context, [
                'expectedUpdatedAt' => $expectedUpdatedAt,
                'refresh' => $e->getRefresh(),
            ], 'shopware');

            return new JsonResponse([
                'status' => 'error',
                'code' => 'CONCURRENT_MODIFICATION',
                'message' => $e->getMessage(),
                'refresh' => $e->getRefresh(),
            ], Response::HTTP_CONFLICT);
        }

        $this->auditLogService->log('liefertermin_lieferant_updated', 'lieferzeiten_position', $positionId, $context, [
            'from' => $range['from']->format('Y-m-d'),
            'to' => $range['to']->format('Y-m-d'),
            'expectedUpdatedAt' => $expectedUpdatedAt,
        ], 'shopware');

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/position/{positionId}/neuer-liefertermin',
        name: 'api.admin.lieferzeiten.position.neuer_liefertermin',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function updateNeuerLiefertermin(string $positionId, Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validatePositionId($positionId);
        if ($validationError !== null) {
            return $validationError;
        }

        $payload = $request->toArray();
        $expectedUpdatedAt = trim((string) ($payload['updatedAt'] ?? ''));
        if ($expectedUpdatedAt === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'updatedAt is required for optimistic concurrency control'], Response::HTTP_BAD_REQUEST);
        }

        $range = $this->extractDateRange($payload);
        if ($range === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'from/to are required date values'], Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->validateRange($range['from'], $range['to'], 1, 4);
        if ($validationError !== null) {
            return $validationError;
        }

        $supplierRange = $this->positionWriteService->getLatestLieferterminLieferantRange($positionId);
        if ($supplierRange === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'Supplier delivery date range must be saved first'], Response::HTTP_BAD_REQUEST);
        }

        if ($range['from'] < $supplierRange['from'] || $range['to'] > $supplierRange['to']) {
            return new JsonResponse(['status' => 'error', 'message' => 'New delivery date range must be inside supplier delivery range'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->positionWriteService->updateNeuerLiefertermin($positionId, $range['from'], $range['to'], $expectedUpdatedAt, $context);
        } catch (WriteEndpointConflictException $e) {
            $this->auditLogService->log('neuer_liefertermin_update_conflict', 'lieferzeiten_position', $positionId, $context, [
                'expectedUpdatedAt' => $expectedUpdatedAt,
                'refresh' => $e->getRefresh(),
            ], 'shopware');

            return new JsonResponse([
                'status' => 'error',
                'code' => 'CONCURRENT_MODIFICATION',
                'message' => $e->getMessage(),
                'refresh' => $e->getRefresh(),
            ], Response::HTTP_CONFLICT);
        }

        $this->auditLogService->log('neuer_liefertermin_updated', 'lieferzeiten_position', $positionId, $context, [
            'from' => $range['from']->format('Y-m-d'),
            'to' => $range['to']->format('Y-m-d'),
            'expectedUpdatedAt' => $expectedUpdatedAt,
        ], 'shopware');

        return new JsonResponse(['status' => 'ok']);
    }


    #[Route(
        path: '/api/_action/lieferzeiten/paket/{paketId}/neuer-liefertermin',
        name: 'api.admin.lieferzeiten.paket.neuer_liefertermin',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function updateNeuerLieferterminByPaket(string $paketId, Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validatePaketId($paketId);
        if ($validationError !== null) {
            return $validationError;
        }

        $payload = $request->toArray();
        $expectedUpdatedAt = trim((string) ($payload['updatedAt'] ?? ''));
        if ($expectedUpdatedAt === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'updatedAt is required for optimistic concurrency control'], Response::HTTP_BAD_REQUEST);
        }

        $range = $this->extractDateRange($payload);
        if ($range === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'from/to are required date values'], Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->validateRange($range['from'], $range['to'], 1, 4);
        if ($validationError !== null) {
            return $validationError;
        }

        if (!$this->positionWriteService->canUpdateNeuerLieferterminForPaket($paketId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Paket status does not allow editing the new delivery date'], Response::HTTP_BAD_REQUEST);
        }

        $supplierRange = $this->positionWriteService->getSupplierRangeBoundsByPaketId($paketId);
        if ($supplierRange === null) {
            return new JsonResponse(['status' => 'error', 'message' => 'Supplier delivery date range must be saved first for all positions of this paket'], Response::HTTP_BAD_REQUEST);
        }

        if ($range['from'] < $supplierRange['from'] || $range['to'] > $supplierRange['to']) {
            return new JsonResponse(['status' => 'error', 'message' => 'New delivery date range must be inside supplier delivery range'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->positionWriteService->updateNeuerLieferterminByPaket($paketId, $range['from'], $range['to'], $expectedUpdatedAt, $context);
        } catch (WriteEndpointConflictException $e) {
            $this->auditLogService->log('neuer_liefertermin_paket_update_conflict', 'lieferzeiten_paket', $paketId, $context, [
                'expectedUpdatedAt' => $expectedUpdatedAt,
                'refresh' => $e->getRefresh(),
            ], 'shopware');

            return new JsonResponse([
                'status' => 'error',
                'code' => 'CONCURRENT_MODIFICATION',
                'message' => $e->getMessage(),
                'refresh' => $e->getRefresh(),
            ], Response::HTTP_CONFLICT);
        }

        $this->auditLogService->log('neuer_liefertermin_paket_updated', 'lieferzeiten_paket', $paketId, $context, [
            'from' => $range['from']->format('Y-m-d'),
            'to' => $range['to']->format('Y-m-d'),
            'expectedUpdatedAt' => $expectedUpdatedAt,
        ], 'shopware');

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/position/{positionId}/comment',
        name: 'api.admin.lieferzeiten.position.comment',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function updateComment(string $positionId, Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validatePositionId($positionId);
        if ($validationError !== null) {
            return $validationError;
        }

        $payload = $request->toArray();
        $comment = trim((string) ($payload['comment'] ?? ''));
        $expectedUpdatedAt = trim((string) ($payload['updatedAt'] ?? ''));
        if ($expectedUpdatedAt === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'updatedAt is required for optimistic concurrency control'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->positionWriteService->updateComment($positionId, $comment, $expectedUpdatedAt, $context);
        } catch (WriteEndpointConflictException $e) {
            $this->auditLogService->log('comment_update_conflict', 'lieferzeiten_position', $positionId, $context, [
                'expectedUpdatedAt' => $expectedUpdatedAt,
                'refresh' => $e->getRefresh(),
            ], 'shopware');

            return new JsonResponse([
                'status' => 'error',
                'code' => 'CONCURRENT_MODIFICATION',
                'message' => $e->getMessage(),
                'refresh' => $e->getRefresh(),
            ], Response::HTTP_CONFLICT);
        }

        $this->auditLogService->log('comment_updated', 'lieferzeiten_position', $positionId, $context, [
            'comment' => $comment,
            'expectedUpdatedAt' => $expectedUpdatedAt,
        ], 'shopware');

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/position/{positionId}/additional-delivery-request',
        name: 'api.admin.lieferzeiten.position.additional_delivery_request',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function createAdditionalDeliveryRequest(string $positionId, Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validatePositionId($positionId);
        if ($validationError !== null) {
            return $validationError;
        }

        $payload = $request->toArray();
        $initiator = trim((string) ($payload['initiator'] ?? 'system')) ?: 'system';

        $this->positionWriteService->createAdditionalDeliveryRequest($positionId, $initiator, $context);
        $this->auditLogService->log('additional_delivery_request_created', 'lieferzeiten_position', $positionId, $context, ['initiator' => $initiator], 'shopware');

        return new JsonResponse(['status' => 'ok']);
    }


    /**
     * @param array<string, mixed> $payload
     * @return array{from: \DateTimeImmutable, to: \DateTimeImmutable}|null
     */
    private function extractDateRange(array $payload): ?array
    {
        $fromValue = trim((string) ($payload['from'] ?? ''));
        $toValue = trim((string) ($payload['to'] ?? ''));

        if ($fromValue === '' || $toValue === '') {
            return null;
        }

        try {
            $from = new \DateTimeImmutable($fromValue . ' 00:00:00');
            $to = new \DateTimeImmutable($toValue . ' 00:00:00');
        } catch (\Throwable) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    private function validateRange(\DateTimeImmutable $from, \DateTimeImmutable $to, int $minDays, int $maxDays): ?JsonResponse
    {
        if ($to < $from) {
            return new JsonResponse(['status' => 'error', 'message' => 'to must be greater than or equal to from'], Response::HTTP_BAD_REQUEST);
        }

        $now = new \DateTimeImmutable('today');
        $startDiff = (int) $now->diff($from)->format('%r%a');
        if ($startDiff < 1) {
            return new JsonResponse(['status' => 'error', 'message' => 'from must be at least +1 day'], Response::HTTP_BAD_REQUEST);
        }

        $rangeDays = (int) $from->diff($to)->format('%a') + 1;
        if ($rangeDays < $minDays || $rangeDays > $maxDays) {
            return new JsonResponse(['status' => 'error', 'message' => sprintf('range must be between %d and %d days', $minDays, $maxDays)], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    private function validatePositionId(string $positionId): ?JsonResponse
    {
        if (!Uuid::isValid($positionId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid position id'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    private function validatePaketId(string $paketId): ?JsonResponse
    {
        if (!Uuid::isValid($paketId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid paket id'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }
}
