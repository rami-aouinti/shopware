<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Controller;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\LieferzeitenOrderStatusWriteService;
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
class LieferzeitenOrderStatusController extends AbstractController
{
    public function __construct(
        private readonly LieferzeitenOrderStatusWriteService $orderStatusWriteService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    #[Route(
        path: '/api/_action/lieferzeiten/orders/{paketId}/status',
        name: 'api.admin.lieferzeiten.orders.status.update',
        defaults: ['_acl' => ['lieferzeiten.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function updateOrderStatus(string $paketId, Request $request, Context $context): JsonResponse
    {
        if (!Uuid::isValid($paketId)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid order id'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->toArray();
        $status = (int) ($payload['status'] ?? 0);

        if (!in_array($status, [7, 8], true)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Only statuses 7 and 8 are allowed'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->orderStatusWriteService->updateOrderStatus($paketId, $status, $context);
        } catch (\RuntimeException) {
            return new JsonResponse(['status' => 'error', 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $this->auditLogService->log('order_status_updated', 'lieferzeiten_paket', $paketId, $context, [
            'targetStatus' => $status,
            'triggerSource' => 'lms_user',
        ], 'shopware');

        return new JsonResponse(['status' => 'ok', 'data' => $data]);
    }
}
