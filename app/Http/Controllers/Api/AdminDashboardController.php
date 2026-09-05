<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\AdminDashboardServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardServiceInterface $adminDashboardService,
    ) {}

    /**
     * Dashboard completo da plataforma.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->adminDashboardService->getOverview(),
        ]);
    }

    /**
     * Apenas as métricas principais.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->adminDashboardService->getStats(),
        ]);
    }

    public function organizations(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->adminDashboardService->getTopOrganizations(
                (int) $request->input('limit', 20)
            ),
        ]);
    }

    public function vendors(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->adminDashboardService->getTopVendors(
                (int) $request->input('limit', 20)
            ),
        ]);
    }

    public function geographic(): JsonResponse
    {
        return response()->json([
            'data' => $this->adminDashboardService->getGeographicDistribution(),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->adminDashboardService->getCategoriesDemand(
                (int) $request->input('limit', 20)
            ),
        ]);
    }
}
