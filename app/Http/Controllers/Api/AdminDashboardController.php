<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Dashboard completo do Super Admin
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'stats' => $this->getStats(),
                'mrr_evolution' => $this->getMrrEvolution(12),
                'organizations_growth' => $this->getOrganizationsGrowth(12),
                'gmv_evolution' => $this->getGmvEvolution(12),
                'top_organizations' => $this->getTopOrganizations(10),
                'top_vendors' => $this->getTopVendors(10),
                'geographic_distribution' => $this->getGeographicDistribution(),
                'categories_demand' => $this->getCategoriesDemand(10),
                'recent_activity' => $this->getRecentActivity(10),
            ],
        ]);
    }

    /**
     * Apenas estatísticas principais
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->getStats()]);
    }

    /**
     * Métricas principais da plataforma
     */
    private function getStats(): array
    {
        // Organizações
        $totalOrganizations = Organization::where('is_active', true)->count();
        $newOrganizationsMonth = Organization::where('is_active', true)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Churn (organizações que cancelaram este mês)
        $churned = Organization::where('subscription_status', 'canceled')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $churnRate = $totalOrganizations > 0
            ? round(($churned / $totalOrganizations) * 100, 2)
            : 0;

        // MRR (Monthly Recurring Revenue) - baseado em planos
        // Use DB-level aggregation to avoid loading all organizations into memory
        $mrr = Organization::where('is_active', true)
            ->where('subscription_status', 'active')
            ->selectRaw("SUM(CASE
                WHEN subscription_plan = 'starter' THEN 199
                WHEN subscription_plan = 'professional' THEN 499
                WHEN subscription_plan = 'enterprise' THEN 1499
                ELSE 0
            END) as total_mrr")
            ->value('total_mrr') ?? 0;

        // GMV (Gross Merchandise Value) - total de propostas aprovadas
        $gmvThisMonth = Proposal::whereIn('status', ['approved', 'contracted'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_value');

        $gmvTotal = Proposal::whereIn('status', ['approved', 'contracted'])
            ->sum('total_value');

        // Fornecedores
        $totalVendors = Vendor::where('is_active', true)->count();
        $verifiedVendors = Vendor::where('is_active', true)
            ->where('is_verified', true)
            ->count();

        // Eventos
        $totalEvents = Event::count();
        $activeEvents = Event::where('status', 'active')->count();

        // Taxa de conversão (cotações que viraram contratos)
        $totalQuotes = QuoteRequest::count();
        $convertedQuotes = QuoteRequest::whereHas('proposals', fn($q) => $q->where('proposals.status', 'contracted'))->count();
        $conversionRate = $totalQuotes > 0
            ? round(($convertedQuotes / $totalQuotes) * 100, 1)
            : 0;

        // Tempo médio de resposta dos fornecedores (em horas)
        // Use database-agnostic calculation instead of TIMESTAMPDIFF
        $avgResponseTime = 0;
        $responseTimes = DB::table('proposals')
            ->join('quote_request_vendor', function ($join) {
                $join->on('proposals.quote_request_id', '=', 'quote_request_vendor.quote_request_id')
                     ->on('proposals.vendor_id', '=', 'quote_request_vendor.vendor_id');
            })
            ->whereNotNull('quote_request_vendor.sent_at')
            ->select('quote_request_vendor.sent_at', 'proposals.created_at')
            ->limit(500)
            ->get();

        if ($responseTimes->isNotEmpty()) {
            $totalHours = $responseTimes->sum(function ($row) {
                return Carbon::parse($row->created_at)->diffInHours(Carbon::parse($row->sent_at));
            });
            $avgResponseTime = $totalHours / $responseTimes->count();
        }

        // Active users (based on recent token usage)
        $activeUsers = User::where('is_active', true)
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        return [
            'total_organizations' => $totalOrganizations,
            'new_organizations_month' => $newOrganizationsMonth,
            'churn_rate' => $churnRate,
            'mrr' => (float) $mrr,
            'gmv_this_month' => (float) $gmvThisMonth,
            'gmv_total' => (float) $gmvTotal,
            'total_vendors' => $totalVendors,
            'verified_vendors' => $verifiedVendors,
            'total_events' => $totalEvents,
            'active_events' => $activeEvents,
            'conversion_rate' => $conversionRate,
            'avg_response_time_hours' => round($avgResponseTime, 1),
            'active_users_30d' => $activeUsers,
        ];
    }

    /**
     * Evolução do MRR nos últimos N meses
     */
    private function getMrrEvolution(int $months): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');

            // Use DB-level aggregation instead of loading all orgs into memory per month
            $mrr = Organization::where('is_active', true)
                ->where('subscription_status', 'active')
                ->where('created_at', '<=', $date->endOfMonth())
                ->selectRaw("SUM(CASE
                    WHEN subscription_plan = 'starter' THEN 199
                    WHEN subscription_plan = 'professional' THEN 499
                    WHEN subscription_plan = 'enterprise' THEN 1499
                    ELSE 0
                END) as total_mrr")
                ->value('total_mrr') ?? 0;

            $data[] = [
                'month' => $month,
                'label' => $date->format('M/Y'),
                'value' => (float) $mrr,
            ];
        }

        return $data;
    }

    /**
     * Crescimento de organizações (novas vs churn)
     */
    private function getOrganizationsGrowth(int $months): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $newOrgs = Organization::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $churned = Organization::where('subscription_status', 'canceled')
                ->whereMonth('updated_at', $date->month)
                ->whereYear('updated_at', $date->year)
                ->count();

            $data[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M/Y'),
                'new_organizations' => $newOrgs,
                'churned' => $churned,
                'net' => $newOrgs - $churned,
            ];
        }

        return $data;
    }

    /**
     * Evolução do GMV nos últimos N meses
     */
    private function getGmvEvolution(int $months): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $gmv = Proposal::whereIn('status', ['approved', 'contracted'])
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('total_value');

            $data[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M/Y'),
                'value' => (float) $gmv,
            ];
        }

        return $data;
    }

    /**
     * Top organizações por GMV
     */
    private function getTopOrganizations(int $limit): array
    {
        // Pre-fetch GMV per organization in a single query to avoid N+1
        $gmvByOrg = DB::table('proposals')
            ->join('quote_requests', 'proposals.quote_request_id', '=', 'quote_requests.id')
            ->join('events', 'quote_requests.event_id', '=', 'events.id')
            ->whereIn('proposals.status', ['approved', 'contracted'])
            ->groupBy('events.organization_id')
            ->selectRaw('events.organization_id, SUM(proposals.total_value) as gmv')
            ->pluck('gmv', 'organization_id');

        return Organization::where('is_active', true)
            ->withCount(['events', 'users'])
            ->get()
            ->map(function ($org) use ($gmvByOrg) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'subscription_plan' => $org->subscription_plan,
                    'events_count' => $org->events_count,
                    'users_count' => $org->users_count,
                    'gmv' => (float) ($gmvByOrg[$org->id] ?? 0),
                    'city' => $org->city,
                    'state' => $org->state,
                ];
            })
            ->sortByDesc('gmv')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Top fornecedores por faturamento
     */
    private function getTopVendors(int $limit): array
    {
        // Pre-fetch proposal stats per vendor in a single query to avoid N+1 (was 3 queries per vendor)
        $vendorStats = DB::table('proposals')
            ->groupBy('vendor_id')
            ->selectRaw("
                vendor_id,
                COUNT(*) as proposals_count,
                SUM(CASE WHEN status IN ('approved', 'contracted') THEN total_value ELSE 0 END) as revenue,
                SUM(CASE WHEN status IN ('approved', 'contracted') THEN 1 ELSE 0 END) as approved_count
            ")
            ->get()
            ->keyBy('vendor_id');

        return Vendor::where('is_active', true)
            ->get()
            ->map(function ($vendor) use ($vendorStats) {
                $stats = $vendorStats[$vendor->id] ?? null;
                $proposalsCount = $stats->proposals_count ?? 0;
                $approvedCount = $stats->approved_count ?? 0;
                $revenue = $stats->revenue ?? 0;

                $approvalRate = $proposalsCount > 0
                    ? round(($approvedCount / $proposalsCount) * 100, 1)
                    : 0;

                return [
                    'id' => $vendor->id,
                    'trade_name' => $vendor->trade_name,
                    'city' => $vendor->city,
                    'state' => $vendor->state,
                    'revenue' => (float) $revenue,
                    'proposals_count' => (int) $proposalsCount,
                    'approval_rate' => $approvalRate,
                    'average_rating' => (float) $vendor->average_rating,
                    'is_verified' => $vendor->is_verified,
                ];
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Distribuição geográfica de organizações e fornecedores
     */
    private function getGeographicDistribution(): array
    {
        $organizations = Organization::where('is_active', true)
            ->whereNotNull('state')
            ->selectRaw('state, COUNT(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();

        $vendors = Vendor::where('is_active', true)
            ->whereNotNull('state')
            ->selectRaw('state, COUNT(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();

        // Combinar estados únicos
        $allStates = array_unique(array_merge(array_keys($organizations), array_keys($vendors)));
        sort($allStates);

        return array_map(fn($state) => [
            'state' => $state,
            'organizations' => $organizations[$state] ?? 0,
            'vendors' => $vendors[$state] ?? 0,
        ], $allStates);
    }

    /**
     * Categorias mais demandadas (por número de cotações)
     */
    private function getCategoriesDemand(int $limit): array
    {
        return Category::where('is_active', true)
            ->withCount(['vendors', 'quoteRequests'])
            ->orderByDesc('quote_requests_count')
            ->limit($limit)
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon,
                'vendors_count' => $cat->vendors_count,
                'quote_requests_count' => $cat->quote_requests_count,
            ])
            ->toArray();
    }

    /**
     * Atividades recentes na plataforma
     */
    private function getRecentActivity(int $limit): array
    {
        $activities = [];

        // Novas organizações
        Organization::where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->each(function ($org) use (&$activities) {
                $activities[] = [
                    'type' => 'new_organization',
                    'message' => "Nova organização: {$org->name}",
                    'created_at' => $org->created_at->toISOString(),
                    'entity_id' => $org->id,
                ];
            });

        // Novos fornecedores
        Vendor::where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->each(function ($vendor) use (&$activities) {
                $activities[] = [
                    'type' => 'new_vendor',
                    'message' => "Novo fornecedor: {$vendor->trade_name}",
                    'created_at' => $vendor->created_at->toISOString(),
                    'entity_id' => $vendor->id,
                ];
            });

        // Propostas aprovadas
        Proposal::where('proposals.status', 'approved')
            ->where('proposals.updated_at', '>=', now()->subDays(7))
            ->with(['vendor', 'quoteRequest.event'])
            ->orderByDesc('proposals.updated_at')
            ->limit(5)
            ->get()
            ->each(function ($proposal) use (&$activities) {
                $activities[] = [
                    'type' => 'proposal_approved',
                    'message' => "Proposta aprovada: {$proposal->vendor?->trade_name} - R$ " . number_format($proposal->total_value, 2, ',', '.'),
                    'created_at' => $proposal->updated_at->toISOString(),
                    'entity_id' => $proposal->id,
                ];
            });

        // Ordenar por data e limitar
        usort($activities, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return array_slice($activities, 0, $limit);
    }

    /**
     * Organizações com filtros
     */
    public function organizations(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->getTopOrganizations($request->input('limit', 20)),
        ]);
    }

    /**
     * Fornecedores com filtros
     */
    public function vendors(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->getTopVendors($request->input('limit', 20)),
        ]);
    }

    /**
     * Distribuição geográfica
     */
    public function geographic(): JsonResponse
    {
        return response()->json([
            'data' => $this->getGeographicDistribution(),
        ]);
    }

    /**
     * Categorias mais demandadas
     */
    public function categories(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->getCategoriesDemand($request->input('limit', 20)),
        ]);
    }
}
