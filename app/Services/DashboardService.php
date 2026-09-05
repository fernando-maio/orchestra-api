<?php

namespace App\Services;

use App\Contracts\Repositories\DashboardRepositoryInterface;
use App\Contracts\Services\DashboardServiceInterface;

class DashboardService implements DashboardServiceInterface
{
    /**
     * Rotulos dos status de proposta, na ordem em que aparecem no gráfico.
     */
    private const PROPOSAL_STATUS_LABELS = [
        'draft' => 'Rascunho',
        'pending' => 'Pendente',
        'under_review' => 'Em Análise',
        'approved' => 'Aprovada',
        'rejected' => 'Rejeitada',
        'contracted' => 'Contratada',
    ];

    public function __construct(
        private readonly DashboardRepositoryInterface $repository,
    ) {}

    public function getStats(?string $organizationId): array
    {
        $recentQuotes = $this->repository->countQuotesSince($organizationId, 30);
        $respondedQuotes = $this->repository->countRespondedQuotesSince($organizationId, 30);

        return [
            'active_events' => $this->repository->countEventsByStatus($organizationId, 'active'),
            'total_vendors' => $this->repository->countVendors($organizationId),
            'open_quotes' => $this->repository->countQuotesByStatus($organizationId, 'open'),
            'pending_proposals' => $this->repository->countProposalsByStatus($organizationId, 'pending'),
            'events_this_month' => $this->repository->countEventsInMonth(
                $organizationId, now()->month, now()->year
            ),
            'active_budget' => $this->repository->sumActiveEventsBudget($organizationId),
            'verified_vendors' => $this->repository->countVendors($organizationId, true),
            'response_rate' => $recentQuotes > 0
                ? round(($respondedQuotes / $recentQuotes) * 100, 1)
                : 0,
        ];
    }

    public function getOverview(?string $organizationId, int $limit = 5): array
    {
        return [
            'stats' => $this->getStats($organizationId),
            'upcoming_events' => $this->repository
                ->upcomingEvents($organizationId, $limit)
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'start_date' => $event->start_date->toDateString(),
                    'city' => $event->city,
                    'state' => $event->state,
                    'days_until' => now()->diffInDays($event->start_date, false),
                    'estimated_budget' => (float) $event->estimated_budget,
                    'expected_attendees' => $event->expected_attendees,
                ])->toArray(),
            'recent_proposals' => $this->repository
                ->recentProposals($organizationId, $limit)
                ->map(fn ($proposal) => [
                    'id' => $proposal->id,
                    'vendor_name' => $proposal->vendor?->trade_name,
                    'event_name' => $proposal->quoteRequest?->event?->name,
                    'value' => (float) $proposal->total_value,
                    'status' => $proposal->status,
                    'submitted_at' => $proposal->submitted_at?->toISOString(),
                    'created_at' => $proposal->created_at->toISOString(),
                ])->toArray(),
            'top_vendors' => $this->repository
                ->topVendors($organizationId, $limit)
                ->map(fn ($vendor) => [
                    'id' => $vendor->id,
                    'trade_name' => $vendor->trade_name,
                    'city' => $vendor->city,
                    'state' => $vendor->state,
                    'average_rating' => (float) $vendor->average_rating,
                    'total_ratings' => $vendor->total_ratings,
                    'is_verified' => $vendor->is_verified,
                ])->toArray(),
        ];
    }

    public function getBudgetOverview(?string $organizationId): array
    {
        $events = $this->repository->activeEvents($organizationId)->map(function ($event) {
            $spent = $this->repository->sumContractedValueForEvent($event->id);
            $budget = (float) ($event->estimated_budget ?? 0);
            $percentage = $budget > 0 ? min(round(($spent / $budget) * 100, 1), 100) : 0;

            return [
                'id' => $event->id,
                'name' => $event->name,
                'estimated_budget' => $budget,
                'spent' => $spent,
                'remaining' => max($budget - $spent, 0),
                'percentage' => $percentage,
                'status' => $this->budgetStatus($budget, $spent),
            ];
        });

        $totalBudget = $events->sum('estimated_budget');
        $totalSpent = $events->sum('spent');

        return [
            'events' => $events->toArray(),
            'totals' => [
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'savings' => max($totalBudget - $totalSpent, 0),
                'percentage' => $totalBudget > 0
                    ? round(($totalSpent / $totalBudget) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * O percentual exibido é limitado a 100, então o status precisa ser
     * calculado a partir dos valores brutos - senão um evento estourado
     * apareceria como 'warning' em vez de 'over_budget'.
     */
    private function budgetStatus(float $budget, float $spent): string
    {
        if ($budget <= 0) {
            return 'ok';
        }

        $percentualReal = ($spent / $budget) * 100;

        return match (true) {
            $percentualReal > 100 => 'over_budget',
            $percentualReal > 80 => 'warning',
            default => 'ok',
        };
    }

    public function getProposalsByStatus(?string $organizationId): array
    {
        $totals = $this->repository->proposalTotalsByStatus($organizationId);

        return collect(self::PROPOSAL_STATUS_LABELS)
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'label' => $label,
                'count' => $totals[$status]['count'] ?? 0,
                'total_value' => $totals[$status]['total_value'] ?? 0.0,
            ])
            ->values()
            ->toArray();
    }

    public function getSpendingByCategory(?string $organizationId): array
    {
        $spending = $this->repository->spendingGroupedByCategory($organizationId);
        $total = (float) $spending->sum('total');

        return [
            'categories' => $spending->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'icon' => $item->icon,
                'color' => $item->color,
                'total' => (float) $item->total,
                'count' => $item->count,
                'percentage' => $total > 0 ? round(($item->total / $total) * 100, 1) : 0,
            ])->toArray(),
            'total' => $total,
        ];
    }

    public function getSpendingHistory(?string $organizationId, int $months = 12): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $data[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M/Y'),
                'spent' => $this->repository->sumContractedValueInMonth(
                    $organizationId, $date->month, $date->year
                ),
                'planned' => $this->repository->sumPlannedBudgetInMonth(
                    $organizationId, $date->month, $date->year
                ),
            ];
        }

        return $data;
    }
}
