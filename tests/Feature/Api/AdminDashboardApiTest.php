<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Vendor;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    // ─── ACCESS CONTROL ────────────────────────────────────────────────

    public function test_regular_admin_cannot_access_admin_dashboard(): void
    {
        $org = $this->createOrganization();
        $this->actingAsAdmin($org);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_organizer_cannot_access_admin_dashboard(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'organizer');

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_viewer_cannot_access_admin_dashboard(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'viewer');

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_admin_dashboard(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertUnauthorized();
    }

    public function test_regular_admin_cannot_access_admin_stats(): void
    {
        $org = $this->createOrganization();
        $this->actingAsAdmin($org);

        $response = $this->getJson('/api/admin/dashboard/stats');

        $response->assertForbidden();
    }

    // ─── INDEX ─────────────────────────────────────────────────────────

    public function test_index_returns_full_dashboard(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats',
                    'mrr_evolution',
                    'organizations_growth',
                    'gmv_evolution',
                    'top_organizations',
                    'top_vendors',
                    'geographic_distribution',
                    'categories_demand',
                    'recent_activity',
                ],
            ]);
    }

    // ─── STATS ─────────────────────────────────────────────────────────

    public function test_stats_returns_platform_metrics(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/admin/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_organizations',
                    'new_organizations_month',
                    'churn_rate',
                    'mrr',
                    'gmv_this_month',
                    'gmv_total',
                    'total_vendors',
                    'verified_vendors',
                    'total_events',
                    'active_events',
                    'conversion_rate',
                    'avg_response_time_hours',
                    'active_users_30d',
                ],
            ]);
    }

    public function test_stats_counts_organizations_correctly(): void
    {
        $this->actingAsSuperAdmin();

        Organization::factory()->count(3)->create(['is_active' => true]);
        Organization::factory()->inactive()->create();

        $response = $this->getJson('/api/admin/dashboard/stats');

        $response->assertOk();
        // 3 active organizations we created (no guarantee about preexisting)
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, $data['total_organizations']);
    }

    public function test_stats_counts_vendors_correctly(): void
    {
        $this->actingAsSuperAdmin();

        Vendor::factory()->count(2)->create(['is_active' => true, 'is_verified' => false]);
        Vendor::factory()->verified()->create(['is_active' => true]);
        Vendor::factory()->inactive()->create();

        $response = $this->getJson('/api/admin/dashboard/stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, $data['total_vendors']);
        $this->assertGreaterThanOrEqual(1, $data['verified_vendors']);
    }

    // ─── ORGANIZATIONS ─────────────────────────────────────────────────

    public function test_organizations_endpoint_returns_data(): void
    {
        $this->actingAsSuperAdmin();
        Organization::factory()->count(2)->create(['is_active' => true]);

        $response = $this->getJson('/api/admin/dashboard/organizations');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_organizations_respects_limit(): void
    {
        $this->actingAsSuperAdmin();
        Organization::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/admin/dashboard/organizations?limit=3');

        $response->assertOk();
        $this->assertLessThanOrEqual(3, count($response->json('data')));
    }

    // ─── VENDORS ───────────────────────────────────────────────────────

    public function test_vendors_endpoint_returns_data(): void
    {
        $this->actingAsSuperAdmin();
        Vendor::factory()->count(2)->create(['is_active' => true]);

        $response = $this->getJson('/api/admin/dashboard/vendors');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── GEOGRAPHIC ────────────────────────────────────────────────────

    public function test_geographic_returns_state_distribution(): void
    {
        $this->actingAsSuperAdmin();

        Organization::factory()->create(['is_active' => true, 'state' => 'SP']);
        Vendor::factory()->create(['is_active' => true, 'state' => 'RJ']);

        $response = $this->getJson('/api/admin/dashboard/geographic');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── CATEGORIES ────────────────────────────────────────────────────

    public function test_categories_returns_demand_data(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/admin/dashboard/categories');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── ALL ADMIN ENDPOINTS FORBIDDEN FOR NON-SUPER-ADMIN ────────────

    public function test_all_admin_endpoints_forbidden_for_regular_admin(): void
    {
        $org = $this->createOrganization();
        $this->actingAsAdmin($org);

        $endpoints = [
            '/api/admin/dashboard',
            '/api/admin/dashboard/stats',
            '/api/admin/dashboard/organizations',
            '/api/admin/dashboard/vendors',
            '/api/admin/dashboard/geographic',
            '/api/admin/dashboard/categories',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertForbidden();
        }
    }
}
