<?php

namespace Tests\Unit\Services;

use App\Contracts\Services\AdminDashboardServiceInterface;
use App\Models\Organization;
use App\Models\Vendor;
use App\Services\AdminDashboardService;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    private AdminDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminDashboardServiceInterface::class);
    }

    // ──────────────────────────────────────────────────
    //  MRR — preços vindos do config
    // ──────────────────────────────────────────────────

    public function test_mrr_sums_prices_from_config(): void
    {
        Organization::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
            'subscription_plan' => 'starter',
        ]);
        Organization::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
            'subscription_plan' => 'enterprise',
        ]);

        // 199 + 1499
        $this->assertSame(1698.0, $this->service->getStats()['mrr']);
    }

    public function test_mrr_follows_config_when_price_changes(): void
    {
        // O ponto do refactor: o preço estava embutido num CASE de SQL,
        // duplicado em duas queries. Mudá-lo não pode exigir editar SQL.
        config(['billing.plan_prices' => ['starter' => 1000]]);

        Organization::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
            'subscription_plan' => 'starter',
        ]);

        $this->assertSame(1000.0, $this->service->getStats()['mrr']);
    }

    public function test_unknown_plan_counts_as_zero_in_mrr(): void
    {
        Organization::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
            'subscription_plan' => 'plano_que_nao_existe',
        ]);

        $this->assertSame(0.0, $this->service->getStats()['mrr']);
    }

    public function test_inactive_subscription_does_not_count_in_mrr(): void
    {
        Organization::factory()->create([
            'is_active' => true,
            'subscription_status' => 'canceled',
            'subscription_plan' => 'enterprise',
        ]);

        $this->assertSame(0.0, $this->service->getStats()['mrr']);
    }

    // ──────────────────────────────────────────────────
    //  Divisões protegidas
    // ──────────────────────────────────────────────────

    public function test_rates_are_zero_on_empty_platform(): void
    {
        // Divisão por zero é o erro óbvio numa plataforma recém-criada.
        $stats = $this->service->getStats();

        $this->assertSame(0.0, $stats['churn_rate']);
        $this->assertSame(0.0, $stats['conversion_rate']);
        $this->assertSame(0.0, $stats['avg_response_time_hours']);
    }

    public function test_approval_rate_is_zero_for_vendor_without_proposals(): void
    {
        Vendor::factory()->create(['is_active' => true]);

        $top = $this->service->getTopVendors(10);

        $this->assertSame(0.0, $top[0]['approval_rate']);
        $this->assertSame(0, $top[0]['proposals_count']);
    }

    // ──────────────────────────────────────────────────
    //  Séries mensais
    // ──────────────────────────────────────────────────

    public function test_monthly_series_return_the_requested_length(): void
    {
        $this->assertCount(6, $this->service->getMrrEvolution(6));
        $this->assertCount(6, $this->service->getGmvEvolution(6));
        $this->assertCount(6, $this->service->getOrganizationsGrowth(6));
    }

    public function test_monthly_series_are_ordered_oldest_first(): void
    {
        $meses = array_column($this->service->getMrrEvolution(4), 'month');
        $ordenado = $meses;
        sort($ordenado);

        $this->assertSame($ordenado, $meses);
    }

    public function test_organizations_growth_computes_net(): void
    {
        Organization::factory()->count(3)->create(['created_at' => now()]);

        $ultimoMes = collect($this->service->getOrganizationsGrowth(1))->last();

        $this->assertSame(3, $ultimoMes['new_organizations']);
        $this->assertSame(
            $ultimoMes['new_organizations'] - $ultimoMes['churned'],
            $ultimoMes['net'],
        );
    }

    // ──────────────────────────────────────────────────
    //  Distribuição geográfica
    // ──────────────────────────────────────────────────

    public function test_geographic_distribution_merges_states_from_both_sides(): void
    {
        Organization::factory()->create(['is_active' => true, 'state' => 'SP']);
        Vendor::factory()->create(['is_active' => true, 'state' => 'RJ']);

        $dist = collect($this->service->getGeographicDistribution())->keyBy('state');

        // Um estado que só tem organização, e outro que só tem fornecedor,
        // precisam aparecer os dois — com zero do lado que falta.
        $this->assertSame(1, $dist['SP']['organizations']);
        $this->assertSame(0, $dist['SP']['vendors']);
        $this->assertSame(0, $dist['RJ']['organizations']);
        $this->assertSame(1, $dist['RJ']['vendors']);
    }

    public function test_geographic_distribution_ignores_records_without_state(): void
    {
        Organization::factory()->create(['is_active' => true, 'state' => null]);

        $this->assertSame([], $this->service->getGeographicDistribution());
    }

    // ──────────────────────────────────────────────────
    //  Ranking
    // ──────────────────────────────────────────────────

    public function test_top_organizations_respects_the_limit(): void
    {
        Organization::factory()->count(5)->create(['is_active' => true]);

        $this->assertCount(3, $this->service->getTopOrganizations(3));
    }

    public function test_top_vendors_are_sorted_by_revenue_desc(): void
    {
        Vendor::factory()->count(3)->create(['is_active' => true]);

        $receitas = array_column($this->service->getTopVendors(10), 'revenue');
        $ordenado = $receitas;
        rsort($ordenado);

        $this->assertSame($ordenado, $receitas);
    }
}
