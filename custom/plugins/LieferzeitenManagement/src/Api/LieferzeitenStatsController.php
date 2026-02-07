<?php declare(strict_types=1);

namespace LieferzeitenManagement\Api;

use LieferzeitenManagement\Service\Stats\LieferzeitenStatsAggregationService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[RouteScope(scopes: ['api'])]
class LieferzeitenStatsController
{
    public function __construct(private readonly LieferzeitenStatsAggregationService $statsService)
    {
    }

    #[Route(
        path: '/api/_action/lieferzeiten-management/stats',
        name: 'api.action.lieferzeiten.management.stats',
        methods: ['GET']
    )]
    public function stats(): JsonResponse
    {
        return new JsonResponse($this->statsService->getStats());
    }
}
