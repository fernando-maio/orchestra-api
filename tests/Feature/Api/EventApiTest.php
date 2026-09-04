<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use Tests\TestCase;

class EventApiTest extends TestCase
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

    public function test_index_returns_paginated_events(): void
    {
        Event::factory()->forOrganization($this->org, $this->admin)->count(3)->create();

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_filters_by_status(): void
    {
        Event::factory()->forOrganization($this->org, $this->admin)->active()->count(2)->create();
        Event::factory()->forOrganization($this->org, $this->admin)->create(['status' => 'draft']);

        $response = $this->getJson('/api/events?status=active');

        $response->assertOk();
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders([])->getJson('/api/events');

        $response->assertUnauthorized();
    }

    // ─── STORE ─────────────────────────────────────────────────────────

    public function test_store_creates_event(): void
    {
        $response = $this->postJson('/api/events', [
            'name' => 'Tech Conference 2026',
            'description' => 'Annual technology conference',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(2)->toDateString(),
            'address' => 'Av Paulista, 1000',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'estimated_budget' => 50000.00,
            'expected_attendees' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tech Conference 2026')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('events', [
            'name' => 'Tech Conference 2026',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/events', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'start_date']);
    }

    public function test_store_validates_start_date_is_future(): void
    {
        $response = $this->postJson('/api/events', [
            'name' => 'Past Event',
            'start_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_store_validates_end_date_after_start(): void
    {
        $response = $this->postJson('/api/events', [
            'name' => 'Bad Dates Event',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->subDay()->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_store_requires_permission(): void
    {
        $org = $this->createOrganization();
        $viewer = $this->createOrgUser($org, 'viewer');
        $this->actingAs($viewer, 'sanctum');

        $response = $this->postJson('/api/events', [
            'name' => 'Test Event',
            'start_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    // ─── SHOW ──────────────────────────────────────────────────────────

    public function test_show_returns_event_with_quote_requests(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create();

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/events/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── UPDATE ────────────────────────────────────────────────────────

    public function test_update_modifies_event(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create([
            'status' => 'draft',
        ]);

        $response = $this->putJson("/api/events/{$event->id}", [
            'name' => 'Updated Event Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Event Name');
    }

    public function test_update_blocks_completed_event(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->completed()->create();

        $response = $this->putJson("/api/events/{$event->id}", [
            'name' => 'Cannot Update',
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_blocks_canceled_event(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->canceled()->create();

        $response = $this->putJson("/api/events/{$event->id}", [
            'name' => 'Cannot Update',
        ]);

        $response->assertUnprocessable();
    }

    // ─── DESTROY ───────────────────────────────────────────────────────

    public function test_destroy_deletes_event_without_quotes(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create();

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertOk();
    }

    public function test_destroy_blocks_deletion_with_quote_requests(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create();
        QuoteRequest::factory()->forEvent($event)->create();

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertUnprocessable();
    }

    public function test_destroy_requires_permission(): void
    {
        $org = $this->createOrganization();
        $organizer = $this->createOrgUser($org, 'organizer');
        $this->actingAs($organizer, 'sanctum');

        $event = Event::factory()->forOrganization($org, $organizer)->create();

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertForbidden();
    }

    // ─── UPDATE STATUS ──────────────────────────────────────────────────

    public function test_update_status_draft_to_active(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create([
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/events/{$event->id}/status", [
            'status' => 'active',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'active']);
    }

    public function test_update_status_active_to_completed(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->active()->create();

        $response = $this->postJson("/api/events/{$event->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'completed']);
    }

    public function test_update_status_active_to_canceled(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->active()->create();

        $response = $this->postJson("/api/events/{$event->id}/status", [
            'status' => 'canceled',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'canceled']);
    }

    public function test_update_status_invalid_transition_rejected(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->completed()->create();

        $response = $this->postJson("/api/events/{$event->id}/status", [
            'status' => 'active',
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_status_validates_status_value(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create();

        $response = $this->postJson("/api/events/{$event->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_status_canceled_to_draft(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->canceled()->create();

        $response = $this->postJson("/api/events/{$event->id}/status", [
            'status' => 'draft',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
    }

    // ─── DUPLICATE ──────────────────────────────────────────────────────

    public function test_duplicate_creates_copy_in_draft_status(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create([
            'name' => 'Original Event',
        ]);

        $response = $this->postJson("/api/events/{$event->id}/duplicate");

        $response->assertCreated();

        $newEvent = Event::where('name', 'Original Event (cópia)')->first();
        $this->assertNotNull($newEvent);
        $this->assertEquals('draft', $newEvent->status);
    }

    // ─── STATISTICS ─────────────────────────────────────────────────────

    public function test_statistics_returns_event_stats(): void
    {
        $event = Event::factory()->forOrganization($this->org, $this->admin)->create();

        $response = $this->getJson("/api/events/{$event->id}/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_quote_requests',
                    'total_proposals',
                    'estimated_budget',
                    'contracted_budget',
                    'budget_progress',
                    'vendor_progress',
                ],
            ]);
    }

    // ─── UPCOMING ───────────────────────────────────────────────────────

    public function test_upcoming_returns_future_events(): void
    {
        Event::factory()->forOrganization($this->org, $this->admin)->upcoming()->count(3)->create();
        Event::factory()->forOrganization($this->org, $this->admin)->past()->create();

        $response = $this->getJson('/api/events/upcoming');

        $response->assertOk();
    }

    public function test_upcoming_respects_limit_parameter(): void
    {
        Event::factory()->forOrganization($this->org, $this->admin)->upcoming()->count(5)->create();

        $response = $this->getJson('/api/events/upcoming?limit=2');

        $response->assertOk();
    }
}
