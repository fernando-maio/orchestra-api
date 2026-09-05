<?php

namespace Tests\Unit\Services;

use App\Contracts\Services\DashboardServiceInterface;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Services\DashboardService;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardServiceInterface::class);
        $this->org = $this->createOrganization();
        // O global scope BelongsToOrganization exige usuario autenticado.
        $this->actingAsOrgUser($this->org, 'admin');
    }

    /**
     * Cria um evento ativo com uma proposta aprovada no valor informado.
     */
    private function eventoComGasto(float $orcamento, float $gasto): Event
    {
        $event = Event::factory()->forOrganization($this->org)->create([
            'status' => 'active',
            'estimated_budget' => $orcamento,
        ]);

        $quote = QuoteRequest::factory()->forEvent($event)->create();
        Proposal::factory()->forQuoteRequest($quote)->approved()->create([
            'total_value' => $gasto,
        ]);

        return $event;
    }

    // ──────────────────────────────────────────────────
    //  getBudgetOverview - status do orçamento
    // ──────────────────────────────────────────────────

    public function test_budget_status_is_over_budget_when_spending_exceeds_budget(): void
    {
        // Regressao: no codigo anterior o percentual era limitado a 100 com
        // min(..., 100) e SO DEPOIS comparado com "> 100", condicao que nunca
        // podia ser verdadeira. O status 'over_budget' era codigo morto, e um
        // evento estourado aparecia como 'warning'.
        $this->eventoComGasto(orcamento: 10000, gasto: 20000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame('over_budget', $overview['events'][0]['status']);
    }

    public function test_budget_status_is_warning_between_80_and_100_percent(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 9000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame('warning', $overview['events'][0]['status']);
    }

    public function test_budget_status_is_ok_below_80_percent(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 5000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame('ok', $overview['events'][0]['status']);
    }

    public function test_budget_percentage_stays_capped_at_100_for_display(): void
    {
        // O percentual exibido continua limitado, para nao quebrar a barra de
        // progresso - o que mudou foi so o calculo do status.
        $this->eventoComGasto(orcamento: 10000, gasto: 20000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame(100.0, (float) $overview['events'][0]['percentage']);
    }

    public function test_budget_status_is_ok_when_budget_is_zero(): void
    {
        // Sem orcamento definido nao ha como estourar; divisao por zero seria
        // o erro real aqui.
        $this->eventoComGasto(orcamento: 0, gasto: 5000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame('ok', $overview['events'][0]['status']);
        // float em todos os casos: antes era int só quando o orçamento era
        // zero, o que deixava o tipo do campo inconsistente.
        $this->assertSame(0.0, $overview['events'][0]['percentage']);
    }

    public function test_budget_totals_are_aggregated(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 4000);
        $this->eventoComGasto(orcamento: 20000, gasto: 6000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame(30000.0, (float) $overview['totals']['total_budget']);
        $this->assertSame(10000.0, (float) $overview['totals']['total_spent']);
        $this->assertSame(20000.0, (float) $overview['totals']['savings']);
    }

    // ──────────────────────────────────────────────────
    //  getBudgetOverview - magnitude do estouro
    // ──────────────────────────────────────────────────

    public function test_over_amount_exposes_how_much_exceeded_the_budget(): void
    {
        // `remaining` fica em zero quando estoura, entao sem over_amount a
        // magnitude nao chegaria a tela: o cliente veria "restante R$ 0" sem
        // saber que passou R$ 10.000.
        $this->eventoComGasto(orcamento: 10000, gasto: 20000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame(10000.0, (float) $overview['events'][0]['over_amount']);
        $this->assertSame(0.0, (float) $overview['events'][0]['remaining']);
    }

    public function test_over_amount_is_zero_when_within_budget(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 4000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame(0.0, (float) $overview['events'][0]['over_amount']);
        $this->assertSame(6000.0, (float) $overview['events'][0]['remaining']);
    }

    public function test_percentage_real_is_not_capped(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 25000);

        $overview = $this->service->getBudgetOverview($this->org->id);

        $this->assertSame(250.0, (float) $overview['events'][0]['percentage_real']);
        // O percentual da barra continua limitado.
        $this->assertSame(100.0, (float) $overview['events'][0]['percentage']);
    }

    public function test_totals_expose_platform_wide_overage(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 15000);
        $this->eventoComGasto(orcamento: 10000, gasto: 2000);

        $totais = $this->service->getBudgetOverview($this->org->id)['totals'];

        // 20.000 de orcamento, 17.000 gastos: no agregado nao estourou.
        $this->assertSame(0.0, (float) $totais['over_amount']);
        $this->assertSame(3000.0, (float) $totais['savings']);
        // Mas um evento estourou, e a tela precisa avisar mesmo assim.
        $this->assertSame(1, $totais['over_budget_count']);
    }

    public function test_totals_over_amount_when_aggregate_exceeds(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 30000);

        $totais = $this->service->getBudgetOverview($this->org->id)['totals'];

        $this->assertSame(20000.0, (float) $totais['over_amount']);
        $this->assertSame(0.0, (float) $totais['savings']);
        $this->assertSame(300.0, (float) $totais['percentage_real']);
        $this->assertSame(100.0, (float) $totais['percentage']);
    }

    // ──────────────────────────────────────────────────
    //  getProposalsByStatus
    // ──────────────────────────────────────────────────

    public function test_proposals_by_status_returns_all_six_statuses_even_when_empty(): void
    {
        // O gráfico precisa das seis colunas sempre, senao a legenda muda de
        // tamanho conforme os dados.
        $data = $this->service->getProposalsByStatus($this->org->id);

        $this->assertCount(6, $data);
        $this->assertSame(
            ['draft', 'pending', 'under_review', 'approved', 'rejected', 'contracted'],
            array_column($data, 'status'),
        );
        $this->assertSame(0, $data[0]['count']);
    }

    public function test_proposals_by_status_counts_and_sums(): void
    {
        $this->eventoComGasto(orcamento: 10000, gasto: 3000);

        $data = collect($this->service->getProposalsByStatus($this->org->id))
            ->keyBy('status');

        $this->assertSame(1, $data['approved']['count']);
        $this->assertSame(3000.0, $data['approved']['total_value']);
    }

    // ──────────────────────────────────────────────────
    //  getStats
    // ──────────────────────────────────────────────────

    public function test_stats_counts_active_events(): void
    {
        Event::factory()->forOrganization($this->org)->count(2)->create(['status' => 'active']);
        Event::factory()->forOrganization($this->org)->create(['status' => 'draft']);

        $stats = $this->service->getStats($this->org->id);

        $this->assertSame(2, $stats['active_events']);
    }

    public function test_response_rate_is_zero_when_there_are_no_quotes(): void
    {
        // Divisao por zero seria o erro obvio aqui.
        $stats = $this->service->getStats($this->org->id);

        $this->assertSame(0, $stats['response_rate']);
    }

    // ──────────────────────────────────────────────────
    //  getSpendingHistory
    // ──────────────────────────────────────────────────

    public function test_spending_history_returns_the_requested_number_of_months(): void
    {
        $history = $this->service->getSpendingHistory($this->org->id, 6);

        $this->assertCount(6, $history);
    }

    public function test_spending_history_is_ordered_from_oldest_to_newest(): void
    {
        $history = $this->service->getSpendingHistory($this->org->id, 3);
        $meses = array_column($history, 'month');

        $ordenado = $meses;
        sort($ordenado);
        $this->assertSame($ordenado, $meses);
    }
}
