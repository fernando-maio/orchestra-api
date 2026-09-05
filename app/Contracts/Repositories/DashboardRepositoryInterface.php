<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

/**
 * Consultas de agregacao do dashboard do cliente.
 *
 * Nao estende BaseRepositoryInterface porque nao pertence a um unico Model:
 * cruza Event, Vendor, QuoteRequest, Proposal e Category.
 *
 * Todos os metodos recebem $organizationId anulavel. Nulo significa "sem
 * recorte de organizacao" - o caso do super-admin olhando a plataforma toda.
 */
interface DashboardRepositoryInterface
{
    public function countEventsByStatus(?string $organizationId, string $status): int;

    public function countEventsInMonth(?string $organizationId, int $month, int $year): int;

    public function sumActiveEventsBudget(?string $organizationId): float;

    public function countVendors(?string $organizationId, ?bool $verified = null): int;

    public function countQuotesByStatus(?string $organizationId, string $status): int;

    public function countQuotesSince(?string $organizationId, int $days): int;

    public function countRespondedQuotesSince(?string $organizationId, int $days): int;

    public function countProposalsByStatus(?string $organizationId, string $status): int;

    public function upcomingEvents(?string $organizationId, int $limit): Collection;

    public function recentProposals(?string $organizationId, int $limit): Collection;

    public function topVendors(?string $organizationId, int $limit): Collection;

    public function activeEvents(?string $organizationId): Collection;

    public function sumContractedValueForEvent(string $eventId): float;

    /**
     * Totais de proposta agrupados por status.
     *
     * Devolve dado puro, e nao models: o consumidor precisa apenas de contagem
     * e soma, e o array permite tipar a forma do retorno.
     *
     * @return array<string, array{count: int, total_value: float}>
     */
    public function proposalTotalsByStatus(?string $organizationId): array;

    public function spendingGroupedByCategory(?string $organizationId): Collection;

    public function sumContractedValueInMonth(?string $organizationId, int $month, int $year): float;

    public function sumPlannedBudgetInMonth(?string $organizationId, int $month, int $year): float;
}
