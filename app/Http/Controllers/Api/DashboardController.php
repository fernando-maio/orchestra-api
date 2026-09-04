<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Retorna estatísticas do dashboard
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        // Super admin pode ver stats de todas organizações ou de uma específica
        if ($user->isSuperAdmin()) {
            $organizationId = $request->input('organization_id');
        }

        $stats = $this->getStats($organizationId);

        return response()->json(['data' => $stats]);
    }

    /**
     * Retorna dados para o dashboard completo
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if ($user->isSuperAdmin()) {
            $organizationId = $request->input('organization_id');
        }

        $stats = $this->getStats($organizationId);
        $upcomingEvents = $this->getUpcomingEvents($organizationId, 5);
        $recentProposals = $this->getRecentProposals($organizationId, 5);
        $topVendors = $this->getTopVendors($organizationId, 5);

        return response()->json([
            'data' => [
                'stats' => $stats,
                'upcoming_events' => $upcomingEvents,
                'recent_proposals' => $recentProposals,
                'top_vendors' => $topVendors,
            ],
        ]);
    }

    /**
     * Calcula estatísticas principais
     */
    private function getStats(?string $organizationId): array
    {
        $eventsQuery = Event::query();
        $vendorsQuery = Vendor::query();
        $quotesQuery = QuoteRequest::query();
        $proposalsQuery = Proposal::query();

        if ($organizationId) {
            $eventsQuery->where('organization_id', $organizationId);
            $vendorsQuery->where('organization_id', $organizationId);
            $quotesQuery->whereHas('event', fn ($q) => $q->where('organization_id', $organizationId));
            $proposalsQuery->whereHas('quoteRequest.event', fn ($q) => $q->where('organization_id', $organizationId));
        }

        // Eventos ativos
        $activeEvents = (clone $eventsQuery)
            ->where('status', 'active')
            ->count();

        // Total de fornecedores
        $totalVendors = (clone $vendorsQuery)
            ->where('is_active', true)
            ->count();

        // Cotações abertas (aguardando resposta)
        $openQuotes = (clone $quotesQuery)
            ->where('status', 'open')
            ->count();

        // Propostas pendentes de análise
        $pendingProposals = (clone $proposalsQuery)
            ->where('status', 'pending')
            ->count();

        // Eventos este mês
        $eventsThisMonth = (clone $eventsQuery)
            ->whereMonth('start_date', now()->month)
            ->whereYear('start_date', now()->year)
            ->count();

        // Budget total dos eventos ativos
        $activeBudget = (clone $eventsQuery)
            ->where('status', 'active')
            ->sum('estimated_budget');

        // Fornecedores verificados
        $verifiedVendors = (clone $vendorsQuery)
            ->where('is_verified', true)
            ->count();

        // Taxa de resposta das cotações (últimos 30 dias)
        $recentQuotes = (clone $quotesQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $respondedQuotes = (clone $quotesQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', ['in_review', 'closed'])
            ->count();

        $responseRate = $recentQuotes > 0
            ? round(($respondedQuotes / $recentQuotes) * 100, 1)
            : 0;

        return [
            'active_events' => $activeEvents,
            'total_vendors' => $totalVendors,
            'open_quotes' => $openQuotes,
            'pending_proposals' => $pendingProposals,
            'events_this_month' => $eventsThisMonth,
            'active_budget' => (float) $activeBudget,
            'verified_vendors' => $verifiedVendors,
            'response_rate' => $responseRate,
        ];
    }

    /**
     * Retorna próximos eventos
     */
    private function getUpcomingEvents(?string $organizationId, int $limit): array
    {
        $query = Event::with('creator')
            ->upcoming()
            ->where('status', 'active')
            ->orderBy('start_date', 'asc')
            ->limit($limit);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->get()->map(fn ($event) => [
            'id' => $event->id,
            'name' => $event->name,
            'start_date' => $event->start_date->toDateString(),
            'city' => $event->city,
            'state' => $event->state,
            'days_until' => now()->diffInDays($event->start_date, false),
            'estimated_budget' => (float) $event->estimated_budget,
            'expected_attendees' => $event->expected_attendees,
        ])->toArray();
    }

    /**
     * Retorna propostas recentes
     */
    private function getRecentProposals(?string $organizationId, int $limit): array
    {
        $query = Proposal::with(['vendor', 'quoteRequest.event'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($organizationId) {
            $query->whereHas('quoteRequest.event', fn ($q) => $q->where('organization_id', $organizationId));
        }

        return $query->get()->map(fn ($proposal) => [
            'id' => $proposal->id,
            'vendor_name' => $proposal->vendor?->trade_name,
            'event_name' => $proposal->quoteRequest?->event?->name,
            'value' => (float) $proposal->total_value,
            'status' => $proposal->status,
            'submitted_at' => $proposal->submitted_at?->toISOString(),
            'created_at' => $proposal->created_at->toISOString(),
        ])->toArray();
    }

    /**
     * Retorna top fornecedores por avaliação
     */
    private function getTopVendors(?string $organizationId, int $limit): array
    {
        $query = Vendor::where('is_active', true)
            ->where('is_verified', true)
            ->orderByDesc('average_rating')
            ->orderByDesc('total_ratings')
            ->limit($limit);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->get()->map(fn ($vendor) => [
            'id' => $vendor->id,
            'trade_name' => $vendor->trade_name,
            'city' => $vendor->city,
            'state' => $vendor->state,
            'average_rating' => (float) $vendor->average_rating,
            'total_ratings' => $vendor->total_ratings,
            'is_verified' => $vendor->is_verified,
        ])->toArray();
    }

    /**
     * Visão geral de orçamento por evento (para gráfico de progress)
     */
    public function budgetOverview(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if ($user->isSuperAdmin()) {
            $organizationId = $request->input('organization_id');
        }

        $events = Event::where('status', 'active')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()
            ->map(function ($event) {
                // Calcular gasto realizado (propostas aprovadas/contratadas)
                $spent = Proposal::whereIn('proposals.status', ['approved', 'contracted'])
                    ->whereHas('quoteRequest', fn ($q) => $q->where('event_id', $event->id))
                    ->sum('total_value');

                $budget = (float) ($event->estimated_budget ?? 0);
                $percentage = $budget > 0 ? min(round(($spent / $budget) * 100, 1), 100) : 0;

                $status = 'ok';
                if ($percentage > 100) {
                    $status = 'over_budget';
                } elseif ($percentage > 80) {
                    $status = 'warning';
                }

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'estimated_budget' => $budget,
                    'spent' => (float) $spent,
                    'remaining' => max($budget - $spent, 0),
                    'percentage' => $percentage,
                    'status' => $status,
                ];
            });

        // Totais
        $totalBudget = $events->sum('estimated_budget');
        $totalSpent = $events->sum('spent');
        $savings = max($totalBudget - $totalSpent, 0);

        return response()->json([
            'data' => [
                'events' => $events->toArray(),
                'totals' => [
                    'total_budget' => $totalBudget,
                    'total_spent' => $totalSpent,
                    'savings' => $savings,
                    'percentage' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
                ],
            ],
        ]);
    }

    /**
     * Propostas agrupadas por status (para gráfico de barras/donut)
     */
    public function proposalsByStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if ($user->isSuperAdmin()) {
            $organizationId = $request->input('organization_id');
        }

        $query = Proposal::query()
            ->when($organizationId, fn ($q) => $q->whereHas('quoteRequest.event', fn ($eq) => $eq->where('organization_id', $organizationId)));

        $statusCounts = $query->selectRaw('status, COUNT(*) as count, SUM(total_value) as total_value')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = ['draft', 'pending', 'under_review', 'approved', 'rejected', 'contracted'];
        $statusLabels = [
            'draft' => 'Rascunho',
            'pending' => 'Pendente',
            'under_review' => 'Em Análise',
            'approved' => 'Aprovada',
            'rejected' => 'Rejeitada',
            'contracted' => 'Contratada',
        ];

        $data = [];
        foreach ($statuses as $status) {
            $item = $statusCounts->get($status);
            $data[] = [
                'status' => $status,
                'label' => $statusLabels[$status],
                'count' => $item?->count ?? 0,
                'total_value' => (float) ($item?->total_value ?? 0),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Gastos por categoria (para gráfico donut)
     */
    public function spendingByCategory(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if ($user->isSuperAdmin()) {
            $organizationId = $request->input('organization_id');
        }

        $spending = DB::table('proposals')
            ->join('quote_requests', 'proposals.quote_request_id', '=', 'quote_requests.id')
            ->join('categories', 'quote_requests.category_id', '=', 'categories.id')
            ->join('events', 'quote_requests.event_id', '=', 'events.id')
            ->whereIn('proposals.status', ['approved', 'contracted'])
            ->when($organizationId, fn ($q) => $q->where('events.organization_id', $organizationId))
            ->selectRaw('categories.id, categories.name, categories.icon, categories.color, SUM(proposals.total_value) as total, COUNT(proposals.id) as count')
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->orderByDesc('total')
            ->get();

        $total = $spending->sum('total');

        $data = $spending->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'icon' => $item->icon,
            'color' => $item->color,
            'total' => (float) $item->total,
            'count' => $item->count,
            'percentage' => $total > 0 ? round(($item->total / $total) * 100, 1) : 0,
        ])->toArray();

        return response()->json([
            'data' => [
                'categories' => $data,
                'total' => (float) $total,
            ],
        ]);
    }

    /**
     * Histórico de gastos mensais (para gráfico de linha)
     */
    public function spendingHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if ($user->isSuperAdmin()) {
            $organizationId = $request->input('organization_id');
        }

        $months = $request->input('months', 12);

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $query = Proposal::whereIn('proposals.status', ['approved', 'contracted'])
                ->whereMonth('proposals.created_at', $date->month)
                ->whereYear('proposals.created_at', $date->year);

            if ($organizationId) {
                $query->whereHas('quoteRequest.event', fn ($q) => $q->where('organization_id', $organizationId));
            }

            $spent = $query->sum('total_value');

            // Budget planejado para eventos daquele mês
            $budgetQuery = Event::whereMonth('start_date', $date->month)
                ->whereYear('start_date', $date->year);

            if ($organizationId) {
                $budgetQuery->where('organization_id', $organizationId);
            }

            $planned = $budgetQuery->sum('estimated_budget');

            $data[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M/Y'),
                'spent' => (float) $spent,
                'planned' => (float) $planned,
            ];
        }

        return response()->json(['data' => $data]);
    }
}
