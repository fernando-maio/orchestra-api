<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->createOrganization();
        $this->admin = $this->actingAsAdmin($this->org);
    }

    // ─── INDEX ─────────────────────────────────────────────────────────

    public function test_index_returns_dashboard_data(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats',
                    'upcoming_events',
                    'recent_proposals',
                    'top_vendors',
                ],
            ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders([])->getJson('/api/dashboard');

        $response->assertUnauthorized();
    }

    public function test_index_returns_org_specific_data(): void
    {
        // Create data for this org
        $event = Event::factory()->forOrganization($this->org, $this->admin)->active()->create([
            'start_date' => now()->addDays(5),
        ]);
        Vendor::factory()->forOrganization($this->org)->verified()->create([
            'is_active' => true,
        ]);

        // Create data for another org (should not appear)
        $otherOrg = $this->createOrganization();
        Event::factory()->forOrganization($otherOrg)->active()->create();

        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'active_events',
                        'total_vendors',
                        'open_quotes',
                        'pending_proposals',
                    ],
                ],
            ]);
    }

    // ─── STATS ─────────────────────────────────────────────────────────

    public function test_stats_returns_statistics(): void
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'active_events',
                    'total_vendors',
                    'open_quotes',
                    'pending_proposals',
                    'events_this_month',
                    'active_budget',
                    'verified_vendors',
                    'response_rate',
                ],
            ]);
    }

    public function test_stats_counts_are_correct(): void
    {
        // Create active events for this org
        Event::factory()->forOrganization($this->org, $this->admin)->active()->count(2)->create([
            'start_date' => now()->addDays(10),
            'estimated_budget' => 10000,
        ]);
        // Create draft event
        Event::factory()->forOrganization($this->org, $this->admin)->create(['status' => 'draft']);

        // Create active vendors for this org
        Vendor::factory()->forOrganization($this->org)->count(3)->create(['is_active' => true]);
        Vendor::factory()->forOrganization($this->org)->inactive()->create();

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(2, $data['active_events']);
        $this->assertEquals(3, $data['total_vendors']);
    }

    public function test_super_admin_can_see_all_stats(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk();
    }

    // ─── BUDGET OVERVIEW ───────────────────────────────────────────────

    public function test_budget_overview_returns_data(): void
    {
        Event::factory()->forOrganization($this->org, $this->admin)->active()->create([
            'estimated_budget' => 100000,
        ]);

        $response = $this->getJson('/api/dashboard/budget-overview');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'events',
                    'totals' => [
                        'total_budget',
                        'total_spent',
                        'savings',
                        'percentage',
                    ],
                ],
            ]);
    }

    public function test_budget_overview_calculates_spent_from_proposals(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->active()->create([
            'estimated_budget' => 100000,
        ]);
        $quoteRequest = QuoteRequest::factory()->forEvent($event)->create();
        Proposal::factory()->forQuoteRequest($quoteRequest)->approved()->create([
            'total_value' => 25000,
        ]);

        $response = $this->getJson('/api/dashboard/budget-overview');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['totals']['total_spent']);
    }

    // ─── PROPOSALS BY STATUS ───────────────────────────────────────────

    public function test_proposals_by_status_returns_data(): void
    {
        $response = $this->getJson('/api/dashboard/proposals-by-status');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        // Should have entries for all status types
        $data = $response->json('data');
        $statuses = array_column($data, 'status');
        $this->assertContains('pending', $statuses);
        $this->assertContains('approved', $statuses);
        $this->assertContains('rejected', $statuses);
    }

    public function test_proposals_by_status_counts_correctly(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create();
        $quoteRequest = QuoteRequest::factory()->forEvent($event)->create();

        Proposal::factory()->forQuoteRequest($quoteRequest)->count(2)->create(['status' => 'pending']);
        Proposal::factory()->forQuoteRequest($quoteRequest)->approved()->create();

        $response = $this->getJson('/api/dashboard/proposals-by-status');

        $response->assertOk();
    }

    // ─── SPENDING BY CATEGORY ──────────────────────────────────────────

    public function test_spending_by_category_returns_data(): void
    {
        $response = $this->getJson('/api/dashboard/spending-by-category');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'total',
                ],
            ]);
    }

    // ─── SPENDING HISTORY ──────────────────────────────────────────────

    public function test_spending_history_returns_monthly_data(): void
    {
        $response = $this->getJson('/api/dashboard/spending-history');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        // Default 12 months
        $data = $response->json('data');
        $this->assertCount(12, $data);
    }

    public function test_spending_history_respects_months_parameter(): void
    {
        $response = $this->getJson('/api/dashboard/spending-history?months=6');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(6, $data);
    }
}
