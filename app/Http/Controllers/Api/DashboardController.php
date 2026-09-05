<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\DashboardServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboardService,
    ) {}

    /**
     * Resolve o recorte de organização da requisição.
     *
     * O usuário comum vê apenas a própria organização. O super-admin vê a
     * plataforma inteira por padrão, e pode recortar por uma organização
     * específica via query string.
     */
    private function resolveOrganizationId(Request $request): ?string
    {
        $user = $request->user();

        return $user->isSuperAdmin()
            ? $request->input('organization_id')
            : $user->organization_id;
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getStats($this->resolveOrganizationId($request)),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getOverview($this->resolveOrganizationId($request)),
        ]);
    }

    public function budgetOverview(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getBudgetOverview($this->resolveOrganizationId($request)),
        ]);
    }

    public function proposalsByStatus(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getProposalsByStatus($this->resolveOrganizationId($request)),
        ]);
    }

    public function spendingByCategory(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getSpendingByCategory($this->resolveOrganizationId($request)),
        ]);
    }

    public function spendingHistory(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getSpendingHistory(
                $this->resolveOrganizationId($request),
                (int) $request->input('months', 12),
            ),
        ]);
    }
}
