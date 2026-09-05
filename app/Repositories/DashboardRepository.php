<?php

namespace App\Repositories;

use App\Contracts\Repositories\DashboardRepositoryInterface;
use App\Models\Event;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    /**
     * Recorte de organizacao para Event e Vendor, que tem a coluna direta.
     */
    private function scopeDirect(Builder $query, ?string $organizationId): Builder
    {
        return $query->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId));
    }

    /**
     * Recorte para QuoteRequest, que chega na organizacao pelo evento.
     */
    private function scopeViaEvent(Builder $query, ?string $organizationId): Builder
    {
        return $query->when(
            $organizationId,
            fn ($q) => $q->whereHas('event', fn ($e) => $e->where('organization_id', $organizationId))
        );
    }

    /**
     * Recorte para Proposal, que chega na organizacao pela cotacao e evento.
     */
    private function scopeViaQuoteEvent(Builder $query, ?string $organizationId): Builder
    {
        return $query->when(
            $organizationId,
            fn ($q) => $q->whereHas('quoteRequest.event', fn ($e) => $e->where('organization_id', $organizationId))
        );
    }

    public function countEventsByStatus(?string $organizationId, string $status): int
    {
        return $this->scopeDirect(Event::query(), $organizationId)
            ->where('status', $status)
            ->count();
    }

    public function countEventsInMonth(?string $organizationId, int $month, int $year): int
    {
        return $this->scopeDirect(Event::query(), $organizationId)
            ->whereMonth('start_date', $month)
            ->whereYear('start_date', $year)
            ->count();
    }

    public function sumActiveEventsBudget(?string $organizationId): float
    {
        return (float) $this->scopeDirect(Event::query(), $organizationId)
            ->where('status', 'active')
            ->sum('estimated_budget');
    }

    public function countVendors(?string $organizationId, ?bool $verified = null): int
    {
        $query = $this->scopeDirect(Vendor::query(), $organizationId);

        return $verified === null
            ? $query->where('is_active', true)->count()
            : $query->where('is_verified', $verified)->count();
    }

    public function countQuotesByStatus(?string $organizationId, string $status): int
    {
        return $this->scopeViaEvent(QuoteRequest::query(), $organizationId)
            ->where('status', $status)
            ->count();
    }

    public function countQuotesSince(?string $organizationId, int $days): int
    {
        return $this->scopeViaEvent(QuoteRequest::query(), $organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    public function countRespondedQuotesSince(?string $organizationId, int $days): int
    {
        return $this->scopeViaEvent(QuoteRequest::query(), $organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('status', ['in_review', 'closed'])
            ->count();
    }

    public function countProposalsByStatus(?string $organizationId, string $status): int
    {
        return $this->scopeViaQuoteEvent(Proposal::query(), $organizationId)
            ->where('proposals.status', $status)
            ->count();
    }

    public function upcomingEvents(?string $organizationId, int $limit): Collection
    {
        // O scope upcoming() e aplicado aqui, na query do model, e nao depois
        // do scopeDirect(): o helper devolve Builder generico e o PHPStan
        // perde o tipo Event, deixando de enxergar os scopes.
        return $this->scopeDirect(
            Event::with('creator')->upcoming()->where('status', 'active'),
            $organizationId
        )
            ->orderBy('start_date')
            ->limit($limit)
            ->get();
    }

    public function recentProposals(?string $organizationId, int $limit): Collection
    {
        return $this->scopeViaQuoteEvent(
            Proposal::with(['vendor', 'quoteRequest.event']),
            $organizationId
        )
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function topVendors(?string $organizationId, int $limit): Collection
    {
        return $this->scopeDirect(Vendor::query(), $organizationId)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->orderByDesc('average_rating')
            ->orderByDesc('total_ratings')
            ->limit($limit)
            ->get();
    }

    public function activeEvents(?string $organizationId): Collection
    {
        return $this->scopeDirect(Event::query(), $organizationId)
            ->where('status', 'active')
            ->get();
    }

    public function sumContractedValueForEvent(string $eventId): float
    {
        return (float) Proposal::whereIn('proposals.status', ['approved', 'contracted'])
            ->whereHas('quoteRequest', fn ($q) => $q->where('event_id', $eventId))
            ->sum('total_value');
    }

    public function proposalTotalsByStatus(?string $organizationId): array
    {
        return $this->scopeViaQuoteEvent(Proposal::query(), $organizationId)
            ->selectRaw('status, COUNT(*) as count, SUM(total_value) as total_value')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($linha) {
                // As colunas vem de selectRaw, entao nao existem como
                // propriedade do model e o PHPStan nao tem como conhece-las.
                // Passar por toArray() da uma forma que ele consegue tipar.
                $dados = $linha->toArray();

                return [
                    (string) $dados['status'] => [
                        'count' => (int) $dados['count'],
                        'total_value' => (float) $dados['total_value'],
                    ],
                ];
            })
            ->all();
    }

    public function spendingGroupedByCategory(?string $organizationId): Collection
    {
        return DB::table('proposals')
            ->join('quote_requests', 'proposals.quote_request_id', '=', 'quote_requests.id')
            ->join('categories', 'quote_requests.category_id', '=', 'categories.id')
            ->join('events', 'quote_requests.event_id', '=', 'events.id')
            ->whereIn('proposals.status', ['approved', 'contracted'])
            ->when($organizationId, fn ($q) => $q->where('events.organization_id', $organizationId))
            ->selectRaw('categories.id, categories.name, categories.icon, categories.color, SUM(proposals.total_value) as total, COUNT(proposals.id) as count')
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->orderByDesc('total')
            ->get();
    }

    public function sumContractedValueInMonth(?string $organizationId, int $month, int $year): float
    {
        return (float) $this->scopeViaQuoteEvent(Proposal::query(), $organizationId)
            ->whereIn('proposals.status', ['approved', 'contracted'])
            ->whereMonth('proposals.created_at', $month)
            ->whereYear('proposals.created_at', $year)
            ->sum('total_value');
    }

    public function sumPlannedBudgetInMonth(?string $organizationId, int $month, int $year): float
    {
        return (float) $this->scopeDirect(Event::query(), $organizationId)
            ->whereMonth('start_date', $month)
            ->whereYear('start_date', $year)
            ->sum('estimated_budget');
    }
}
