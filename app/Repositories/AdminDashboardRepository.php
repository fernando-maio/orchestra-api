<?php

namespace App\Repositories;

use App\Contracts\Repositories\AdminDashboardRepositoryInterface;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardRepository implements AdminDashboardRepositoryInterface
{
    /**
     * Status de proposta que contam como receita realizada.
     */
    private const REVENUE_STATUSES = ['approved', 'contracted'];

    public function countActiveOrganizations(): int
    {
        return Organization::where('is_active', true)->count();
    }

    public function countOrganizationsCreatedIn(int $month, int $year): int
    {
        return Organization::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();
    }

    public function countOrganizationsChurnedIn(int $month, int $year): int
    {
        return Organization::where('subscription_status', 'canceled')
            ->whereMonth('updated_at', $month)
            ->whereYear('updated_at', $year)
            ->count();
    }

    public function sumMrr(array $planPrices, ?DateTimeInterface $upTo = null): float
    {
        if ($planPrices === []) {
            return 0.0;
        }

        // O CASE e montado a partir do config, com bindings - os precos nunca
        // sao interpolados na string da query.
        $case = 'SUM(CASE';
        $bindings = [];
        foreach ($planPrices as $plano => $preco) {
            $case .= ' WHEN subscription_plan = ? THEN ?';
            $bindings[] = $plano;
            $bindings[] = $preco;
        }
        $case .= ' ELSE 0 END) as total_mrr';

        return (float) Organization::where('is_active', true)
            ->where('subscription_status', 'active')
            ->when($upTo, fn ($q) => $q->where('created_at', '<=', $upTo))
            ->selectRaw($case, $bindings)
            ->value('total_mrr');
    }

    public function sumGmv(?int $month = null, ?int $year = null): float
    {
        return (float) Proposal::whereIn('status', self::REVENUE_STATUSES)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->sum('total_value');
    }

    public function countVendors(bool $onlyVerified = false): int
    {
        return Vendor::where('is_active', true)
            ->when($onlyVerified, fn ($q) => $q->where('is_verified', true))
            ->count();
    }

    public function countEvents(?string $status = null): int
    {
        return Event::when($status, fn ($q) => $q->where('status', $status))->count();
    }

    public function countQuotes(): int
    {
        return QuoteRequest::count();
    }

    public function countConvertedQuotes(): int
    {
        return QuoteRequest::whereHas(
            'proposals',
            fn ($q) => $q->where('proposals.status', 'contracted')
        )->count();
    }

    public function responseTimeSamples(int $limit = 500): Collection
    {
        // Amostra limitada de proposito: a media so precisa ser representativa,
        // e varrer a tabela inteira nao compensa numa tela de dashboard.
        return DB::table('proposals')
            ->join('quote_request_vendor', function ($join) {
                $join->on('proposals.quote_request_id', '=', 'quote_request_vendor.quote_request_id')
                    ->on('proposals.vendor_id', '=', 'quote_request_vendor.vendor_id');
            })
            ->whereNotNull('quote_request_vendor.sent_at')
            ->select('quote_request_vendor.sent_at', 'proposals.created_at')
            ->limit($limit)
            ->get();
    }

    public function countActiveUsersSince(int $days): int
    {
        return User::where('is_active', true)
            ->where('updated_at', '>=', now()->subDays($days))
            ->count();
    }

    public function gmvGroupedByOrganization(): Collection
    {
        // Uma query so, para nao gerar N+1 ao montar o ranking.
        return DB::table('proposals')
            ->join('quote_requests', 'proposals.quote_request_id', '=', 'quote_requests.id')
            ->join('events', 'quote_requests.event_id', '=', 'events.id')
            ->whereIn('proposals.status', self::REVENUE_STATUSES)
            ->groupBy('events.organization_id')
            ->selectRaw('events.organization_id, SUM(proposals.total_value) as gmv')
            ->pluck('gmv', 'organization_id');
    }

    public function activeOrganizationsWithCounts(): Collection
    {
        return Organization::where('is_active', true)
            ->withCount(['events', 'users'])
            ->get();
    }

    public function proposalStatsGroupedByVendor(): Collection
    {
        $statuses = "'".implode("','", self::REVENUE_STATUSES)."'";

        return DB::table('proposals')
            ->groupBy('vendor_id')
            ->selectRaw("
                vendor_id,
                COUNT(*) as proposals_count,
                SUM(CASE WHEN status IN ({$statuses}) THEN total_value ELSE 0 END) as revenue,
                SUM(CASE WHEN status IN ({$statuses}) THEN 1 ELSE 0 END) as approved_count
            ")
            ->get()
            ->keyBy('vendor_id');
    }

    public function activeVendors(): Collection
    {
        return Vendor::where('is_active', true)->get();
    }

    public function organizationsGroupedByState(): array
    {
        return Organization::where('is_active', true)
            ->whereNotNull('state')
            ->selectRaw('state, COUNT(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();
    }

    public function vendorsGroupedByState(): array
    {
        return Vendor::where('is_active', true)
            ->whereNotNull('state')
            ->selectRaw('state, COUNT(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();
    }

    public function categoriesByDemand(int $limit): Collection
    {
        return Category::where('is_active', true)
            ->withCount(['vendors', 'quoteRequests'])
            ->orderByDesc('quote_requests_count')
            ->limit($limit)
            ->get();
    }

    public function recentOrganizations(int $days, int $limit): Collection
    {
        return Organization::where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function recentVendors(int $days, int $limit): Collection
    {
        return Vendor::where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function recentApprovedProposals(int $days, int $limit): Collection
    {
        return Proposal::where('proposals.status', 'approved')
            ->where('proposals.updated_at', '>=', now()->subDays($days))
            ->with(['vendor', 'quoteRequest.event'])
            ->orderByDesc('proposals.updated_at')
            ->limit($limit)
            ->get();
    }
}
