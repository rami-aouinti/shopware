<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Controller;

use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use LieferzeitenAdmin\Service\Tracking\TrackingHistoryService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
    ) {
    }


    #[Route(
        path: '/api/_action/lieferzeiten/orders',
        name: 'api.admin.lieferzeiten.orders',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function orders(Request $request): JsonResponse
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

        return new JsonResponse($payload);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/sync',
        name: 'api.admin.lieferzeiten.sync',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_POST]
    )]
    public function syncNow(Context $context): JsonResponse
    {
        $this->importService->sync($context, 'on_demand');

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/lieferzeiten/tracking/{carrier}/{trackingNumber}',
        name: 'api.admin.lieferzeiten.tracking-history',
        defaults: ['_acl' => ['admin']],
        methods: [Request::METHOD_GET]
    )]
    public function trackingHistory(string $carrier, string $trackingNumber): JsonResponse
    {
        $response = $this->trackingHistoryService->fetchHistory($carrier, $trackingNumber);

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

}
