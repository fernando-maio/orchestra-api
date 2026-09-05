<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

/**
 * Consultas do dashboard administrativo (visão da plataforma).
 *
 * Diferente do DashboardRepository, aqui não há recorte por organização:
 * estas rotas são exclusivas do super-admin e olham a plataforma inteira.
 */
interface AdminDashboardRepositoryInterface
{
    public function countActiveOrganizations(): int;

    public function countOrganizationsCreatedIn(int $month, int $year): int;

    public function countOrganizationsChurnedIn(int $month, int $year): int;

    /**
     * Soma o MRR das organizações ativas, opcionalmente até uma data de corte.
     *
     * @param  array<string, int|float>  $planPrices  preço mensal por plano
     */
    public function sumMrr(array $planPrices, ?\DateTimeInterface $upTo = null): float;

    public function sumGmv(?int $month = null, ?int $year = null): float;

    public function countVendors(bool $onlyVerified = false): int;

    public function countEvents(?string $status = null): int;

    public function countQuotes(): int;

    public function countConvertedQuotes(): int;

    public function responseTimeSamples(int $limit = 500): Collection;

    public function countActiveUsersSince(int $days): int;

    public function gmvGroupedByOrganization(): Collection;

    public function activeOrganizationsWithCounts(): Collection;

    public function proposalStatsGroupedByVendor(): Collection;

    public function activeVendors(): Collection;

    public function organizationsGroupedByState(): array;

    public function vendorsGroupedByState(): array;

    public function categoriesByDemand(int $limit): Collection;

    public function recentOrganizations(int $days, int $limit): Collection;

    public function recentVendors(int $days, int $limit): Collection;

    public function recentApprovedProposals(int $days, int $limit): Collection;
}
