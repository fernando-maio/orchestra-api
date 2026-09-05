<?php

namespace App\Services;

use App\Contracts\Repositories\AdminDashboardRepositoryInterface;
use App\Contracts\Services\AdminDashboardServiceInterface;
use Carbon\Carbon;

class AdminDashboardService implements AdminDashboardServiceInterface
{
    public function __construct(
        private readonly AdminDashboardRepositoryInterface $repository,
    ) {}

    /**
     * Preço mensal por plano, base do cálculo de MRR.
     *
     * Vem do config e não de um CASE embutido na query, onde estava duplicado
     * em dois lugares.
     *
     * @return array<string, int|float>
     */
    private function planPrices(): array
    {
        return config('billing.plan_prices', []);
    }

    public function getStats(): array
    {
        $totalOrganizations = $this->repository->countActiveOrganizations();
        $churned = $this->repository->countOrganizationsChurnedIn(now()->month, now()->year);
        $totalQuotes = $this->repository->countQuotes();
        $convertedQuotes = $this->repository->countConvertedQuotes();

        return [
            'total_organizations' => $totalOrganizations,
            'new_organizations_month' => $this->repository->countOrganizationsCreatedIn(
                now()->month, now()->year
            ),
            'churn_rate' => $this->percentage($churned, $totalOrganizations, 2),
            'mrr' => $this->repository->sumMrr($this->planPrices()),
            'gmv_this_month' => $this->repository->sumGmv(now()->month, now()->year),
            'gmv_total' => $this->repository->sumGmv(),
            'total_vendors' => $this->repository->countVendors(),
            'verified_vendors' => $this->repository->countVendors(onlyVerified: true),
            'total_events' => $this->repository->countEvents(),
            'active_events' => $this->repository->countEvents('active'),
            'conversion_rate' => $this->percentage($convertedQuotes, $totalQuotes),
            'avg_response_time_hours' => $this->averageResponseTimeHours(),
            'active_users_30d' => $this->repository->countActiveUsersSince(30),
        ];
    }

    /**
     * Percentual protegido contra divisão por zero.
     */
    private function percentage(int|float $parte, int|float $total, int $casas = 1): float
    {
        return $total > 0 ? round(($parte / $total) * 100, $casas) : 0.0;
    }

    /**
     * Tempo médio entre o envio da cotação e a resposta do fornecedor.
     *
     * Calculado em PHP e não com TIMESTAMPDIFF porque os testes rodam em
     * SQLite, que não tem essa função.
     */
    private function averageResponseTimeHours(): float
    {
        $amostras = $this->repository->responseTimeSamples();

        if ($amostras->isEmpty()) {
            return 0.0;
        }

        $totalHoras = $amostras->sum(
            fn ($linha) => Carbon::parse($linha->created_at)
                ->diffInHours(Carbon::parse($linha->sent_at))
        );

        return round($totalHoras / $amostras->count(), 1);
    }

    public function getOverview(): array
    {
        return [
            'stats' => $this->getStats(),
            'mrr_evolution' => $this->getMrrEvolution(12),
            'organizations_growth' => $this->getOrganizationsGrowth(12),
            'gmv_evolution' => $this->getGmvEvolution(12),
            'top_organizations' => $this->getTopOrganizations(10),
            'top_vendors' => $this->getTopVendors(10),
            'geographic_distribution' => $this->getGeographicDistribution(),
            'categories_demand' => $this->getCategoriesDemand(10),
            'recent_activity' => $this->getRecentActivity(10),
        ];
    }

    /**
     * Percorre os últimos N meses, do mais antigo para o mais recente.
     *
     * @param  callable(Carbon): array<string, mixed>  $montaLinha
     */
    private function monthlySeries(int $months, callable $montaLinha): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $data[] = array_merge([
                'month' => $date->format('Y-m'),
                'label' => $date->format('M/Y'),
            ], $montaLinha($date));
        }

        return $data;
    }

    public function getMrrEvolution(int $months): array
    {
        return $this->monthlySeries($months, fn (Carbon $date) => [
            'value' => $this->repository->sumMrr(
                $this->planPrices(),
                $date->copy()->endOfMonth(),
            ),
        ]);
    }

    public function getOrganizationsGrowth(int $months): array
    {
        return $this->monthlySeries($months, function (Carbon $date) {
            $novas = $this->repository->countOrganizationsCreatedIn($date->month, $date->year);
            $churned = $this->repository->countOrganizationsChurnedIn($date->month, $date->year);

            return [
                'new_organizations' => $novas,
                'churned' => $churned,
                'net' => $novas - $churned,
            ];
        });
    }

    public function getGmvEvolution(int $months): array
    {
        return $this->monthlySeries($months, fn (Carbon $date) => [
            'value' => $this->repository->sumGmv($date->month, $date->year),
        ]);
    }

    public function getTopOrganizations(int $limit): array
    {
        $gmvPorOrg = $this->repository->gmvGroupedByOrganization();

        return $this->repository->activeOrganizationsWithCounts()
            ->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'subscription_plan' => $org->subscription_plan,
                'events_count' => $org->events_count,
                'users_count' => $org->users_count,
                'gmv' => (float) ($gmvPorOrg[$org->id] ?? 0),
                'city' => $org->city,
                'state' => $org->state,
            ])
            ->sortByDesc('gmv')
            ->take($limit)
            ->values()
            ->toArray();
    }

    public function getTopVendors(int $limit): array
    {
        $stats = $this->repository->proposalStatsGroupedByVendor();

        return $this->repository->activeVendors()
            ->map(function ($vendor) use ($stats) {
                $dados = $stats[$vendor->id] ?? null;
                $propostas = (int) ($dados->proposals_count ?? 0);
                $aprovadas = (int) ($dados->approved_count ?? 0);

                return [
                    'id' => $vendor->id,
                    'trade_name' => $vendor->trade_name,
                    'city' => $vendor->city,
                    'state' => $vendor->state,
                    'revenue' => (float) ($dados->revenue ?? 0),
                    'proposals_count' => $propostas,
                    'approval_rate' => $this->percentage($aprovadas, $propostas),
                    'average_rating' => (float) $vendor->average_rating,
                    'is_verified' => $vendor->is_verified,
                ];
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->toArray();
    }

    public function getGeographicDistribution(): array
    {
        $organizacoes = $this->repository->organizationsGroupedByState();
        $fornecedores = $this->repository->vendorsGroupedByState();

        $estados = array_unique(array_merge(
            array_keys($organizacoes),
            array_keys($fornecedores),
        ));
        sort($estados);

        return array_map(fn ($uf) => [
            'state' => $uf,
            'organizations' => $organizacoes[$uf] ?? 0,
            'vendors' => $fornecedores[$uf] ?? 0,
        ], $estados);
    }

    public function getCategoriesDemand(int $limit): array
    {
        return $this->repository->categoriesByDemand($limit)
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon,
                'vendors_count' => $cat->vendors_count,
                'quote_requests_count' => $cat->quote_requests_count,
            ])
            ->toArray();
    }

    public function getRecentActivity(int $limit): array
    {
        $dias = 7;
        $porTipo = 5;

        $atividades = collect()
            ->concat($this->repository->recentOrganizations($dias, $porTipo)
                ->map(fn ($org) => [
                    'type' => 'new_organization',
                    'message' => "Nova organização: {$org->name}",
                    'created_at' => $org->created_at->toISOString(),
                    'entity_id' => $org->id,
                ]))
            ->concat($this->repository->recentVendors($dias, $porTipo)
                ->map(fn ($vendor) => [
                    'type' => 'new_vendor',
                    'message' => "Novo fornecedor: {$vendor->trade_name}",
                    'created_at' => $vendor->created_at->toISOString(),
                    'entity_id' => $vendor->id,
                ]))
            ->concat($this->repository->recentApprovedProposals($dias, $porTipo)
                ->map(fn ($proposal) => [
                    'type' => 'proposal_approved',
                    'message' => 'Proposta aprovada: '.$proposal->vendor?->trade_name
                        .' - R$ '.number_format((float) $proposal->total_value, 2, ',', '.'),
                    'created_at' => $proposal->updated_at->toISOString(),
                    'entity_id' => $proposal->id,
                ]));

        return $atividades
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
