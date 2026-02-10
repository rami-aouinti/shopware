<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Controller;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use LieferzeitenAdmin\Service\LieferzeitenPositionWriteService;
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
        private readonly AuditLogService $auditLogService,
    ) {
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
            ],
        );

        $this->auditLogService->log('orders_viewed', 'lieferzeiten_orders', null, $context, [
            'page' => (int) $request->query->get('page', 1),
            'limit' => (int) $request->query->get('limit', 25),
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
        $days = (int) ($payload['days'] ?? 0);
        if ($days < 1 || $days > 14) {
            return new JsonResponse(['status' => 'error', 'message' => 'days must be between 1 and 14'], Response::HTTP_BAD_REQUEST);
        }

        $this->positionWriteService->updateLieferterminLieferant($positionId, $days, $context);
        $this->auditLogService->log('liefertermin_lieferant_updated', 'lieferzeiten_position', $positionId, $context, ['days' => $days], 'shopware');

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
        $days = (int) ($payload['days'] ?? 0);
        if ($days < 1 || $days > 4) {
            return new JsonResponse(['status' => 'error', 'message' => 'days must be between 1 and 4'], Response::HTTP_BAD_REQUEST);
        }

        $this->positionWriteService->updateNeuerLiefertermin($positionId, $days, $context);
        $this->auditLogService->log('neuer_liefertermin_updated', 'lieferzeiten_position', $positionId, $context, ['days' => $days], 'shopware');

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

        $this->positionWriteService->updateComment($positionId, $comment, $context);
        $this->auditLogService->log('comment_updated', 'lieferzeiten_position', $positionId, $context, ['comment' => $comment], 'shopware');

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

    private function validatePositionId(string $positionId): ?JsonResponse
    {
        if (!Uuid::isValid($positionId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid position id'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }
}
