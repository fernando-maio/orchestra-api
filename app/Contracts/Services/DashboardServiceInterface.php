<?php

namespace App\Contracts\Services;

/**
 * Dashboard do cliente (organizacao).
 *
 * Nao estende BaseServiceInterface: nao ha CRUD aqui, so leitura agregada.
 * $organizationId nulo significa "sem recorte" - super-admin vendo a
 * plataforma inteira.
 */
interface DashboardServiceInterface
{
    public function getStats(?string $organizationId): array;

    public function getOverview(?string $organizationId, int $limit = 5): array;

    public function getBudgetOverview(?string $organizationId): array;

    public function getProposalsByStatus(?string $organizationId): array;

    public function getSpendingByCategory(?string $organizationId): array;

    public function getSpendingHistory(?string $organizationId, int $months = 12): array;
}
