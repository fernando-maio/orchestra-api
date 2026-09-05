<?php

namespace App\Contracts\Services;

/**
 * Dashboard administrativo (visão da plataforma Orchestra).
 *
 * Não estende BaseServiceInterface: não há CRUD, só leitura agregada.
 */
interface AdminDashboardServiceInterface
{
    public function getStats(): array;

    public function getOverview(): array;

    public function getMrrEvolution(int $months): array;

    public function getOrganizationsGrowth(int $months): array;

    public function getGmvEvolution(int $months): array;

    public function getTopOrganizations(int $limit): array;

    public function getTopVendors(int $limit): array;

    public function getGeographicDistribution(): array;

    public function getCategoriesDemand(int $limit): array;

    public function getRecentActivity(int $limit): array;
}
