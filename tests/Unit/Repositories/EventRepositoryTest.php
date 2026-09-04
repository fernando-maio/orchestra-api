<?php

namespace Tests\Unit\Repositories;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Repositories\EventRepository;
use Tests\TestCase;

class EventRepositoryTest extends TestCase
{
    private EventRepository $repository;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EventRepository(new Event);
        $this->organization = $this->createOrganization();
        // Authenticate as super-admin to bypass BelongsToOrganization global scope
        $this->actingAsSuperAdmin();
    }

    private function createEvent(array $attributes = []): Event
    {
        $defaults = ['name' => 'Event '.fake()->unique()->randomNumber(5)];

        return Event::factory()
            ->forOrganization($this->organization)
            ->create(array_merge($defaults, $attributes));
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_returns_all_when_no_filters(): void
    {
        $this->createEvent();
        $this->createEvent();

        $result = $this->repository->paginateWithFilters([]);

        $this->assertEquals(2, $result->total());
    }

    public function test_paginate_with_filters_by_organization_id(): void
    {
        $this->createEvent();
        $otherOrg = $this->createOrganization();
        Event::factory()->forOrganization($otherOrg)->create(['name' => 'Other Org Event']);

        $result = $this->repository->paginateWithFilters([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_search_by_name(): void
    {
        $this->createEvent(['name' => 'Annual Gala']);
        $this->createEvent(['name' => 'Corporate Meeting']);

        $result = $this->repository->paginateWithFilters(['search' => 'Gala']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Annual Gala', $result->items()[0]->name);
    }

    public function test_paginate_with_filters_search_by_description(): void
    {
        $this->createEvent(['description' => 'An exciting fundraiser event']);
        $this->createEvent(['description' => 'Regular meeting']);

        $result = $this->repository->paginateWithFilters(['search' => 'fundraiser']);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_search_by_venue_name(): void
    {
        $this->createEvent(['venue_name' => 'Grand Ballroom']);
        $this->createEvent(['venue_name' => 'Conference Room A']);

        $result = $this->repository->paginateWithFilters(['search' => 'Ballroom']);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_by_status(): void
    {
        $this->createEvent(['status' => 'draft']);
        $this->createEvent(['status' => 'active']);
        $this->createEvent(['status' => 'active']);

        $result = $this->repository->paginateWithFilters(['status' => 'active']);

        $this->assertEquals(2, $result->total());
    }

    public function test_paginate_with_filters_by_city(): void
    {
        $this->createEvent(['city' => 'Sao Paulo']);
        $this->createEvent(['city' => 'Rio de Janeiro']);

        $result = $this->repository->paginateWithFilters(['city' => 'Sao Paulo']);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_by_state(): void
    {
        $this->createEvent(['state' => 'SP']);
        $this->createEvent(['state' => 'RJ']);

        $result = $this->repository->paginateWithFilters(['state' => 'SP']);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_by_date_range(): void
    {
        $this->createEvent(['start_date' => '2026-06-01']);
        $this->createEvent(['start_date' => '2026-08-15']);
        $this->createEvent(['start_date' => '2026-12-01']);

        $result = $this->repository->paginateWithFilters([
            'start_from' => '2026-05-01',
            'start_to' => '2026-09-01',
        ]);

        $this->assertEquals(2, $result->total());
    }

    public function test_paginate_with_filters_upcoming_only(): void
    {
        $this->createEvent(['start_date' => now()->addDays(10)]);
        $this->createEvent(['start_date' => now()->subDays(10)]);

        $result = $this->repository->paginateWithFilters(['upcoming' => true]);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_past_only(): void
    {
        $this->createEvent(['start_date' => now()->addDays(10)]);
        $this->createEvent(['start_date' => now()->subDays(10)]);

        $result = $this->repository->paginateWithFilters(['past' => true]);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_respects_per_page(): void
    {
        $this->createEvent();
        $this->createEvent();
        $this->createEvent();

        $result = $this->repository->paginateWithFilters([], 2);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_paginate_with_filters_orders_by_start_date_desc(): void
    {
        $this->createEvent(['name' => 'Early', 'start_date' => '2026-01-01']);
        $this->createEvent(['name' => 'Late', 'start_date' => '2026-12-01']);
        $this->createEvent(['name' => 'Middle', 'start_date' => '2026-06-01']);

        $result = $this->repository->paginateWithFilters([]);

        $this->assertEquals('Late', $result->items()[0]->name);
        $this->assertEquals('Middle', $result->items()[1]->name);
        $this->assertEquals('Early', $result->items()[2]->name);
    }

    public function test_paginate_with_filters_eager_loads_creator_and_organization(): void
    {
        $this->createEvent();

        $result = $this->repository->paginateWithFilters([]);

        $this->assertTrue($result->items()[0]->relationLoaded('creator'));
        $this->assertTrue($result->items()[0]->relationLoaded('organization'));
    }

    // ──────────────────────────────────────────────────
    //  getUpcoming
    // ──────────────────────────────────────────────────

    public function test_get_upcoming_returns_future_events_for_organization(): void
    {
        $this->createEvent(['start_date' => now()->addDays(5)]);
        $this->createEvent(['start_date' => now()->addDays(10)]);
        $this->createEvent(['start_date' => now()->subDays(5)]);

        $otherOrg = $this->createOrganization();
        Event::factory()->forOrganization($otherOrg)->create(['name' => 'Other Event', 'start_date' => now()->addDays(3)]);

        $result = $this->repository->getUpcoming($this->organization->id);

        $this->assertCount(2, $result);
    }

    public function test_get_upcoming_orders_by_start_date_asc(): void
    {
        $this->createEvent(['name' => 'Later', 'start_date' => now()->addDays(20)]);
        $this->createEvent(['name' => 'Sooner', 'start_date' => now()->addDays(5)]);

        $result = $this->repository->getUpcoming($this->organization->id);

        $this->assertEquals('Sooner', $result[0]->name);
        $this->assertEquals('Later', $result[1]->name);
    }

    public function test_get_upcoming_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createEvent(['start_date' => now()->addDays($i + 1)]);
        }

        $result = $this->repository->getUpcoming($this->organization->id, 3);

        $this->assertCount(3, $result);
    }

    // ──────────────────────────────────────────────────
    //  getByStatus
    // ──────────────────────────────────────────────────

    public function test_get_by_status_returns_events_with_given_status(): void
    {
        $this->createEvent(['status' => 'active']);
        $this->createEvent(['status' => 'active']);
        $this->createEvent(['status' => 'draft']);

        $result = $this->repository->getByStatus($this->organization->id, 'active');

        $this->assertCount(2, $result);
        $result->each(fn (Event $e) => $this->assertEquals('active', $e->status));
    }

    public function test_get_by_status_scoped_to_organization(): void
    {
        $this->createEvent(['status' => 'active']);

        $otherOrg = $this->createOrganization();
        Event::factory()->forOrganization($otherOrg)->create(['name' => 'Other Active', 'status' => 'active']);

        $result = $this->repository->getByStatus($this->organization->id, 'active');

        $this->assertCount(1, $result);
    }

    public function test_get_by_status_returns_empty_when_no_match(): void
    {
        $this->createEvent(['status' => 'draft']);

        $result = $this->repository->getByStatus($this->organization->id, 'completed');

        $this->assertCount(0, $result);
    }

    // ──────────────────────────────────────────────────
    //  getWithQuoteRequests
    // ──────────────────────────────────────────────────

    public function test_get_with_quote_requests_returns_event_with_relations(): void
    {
        $event = $this->createEvent();
        $category = Category::factory()->create();
        $qr = QuoteRequest::factory()->forEvent($event, $category)->create();
        Proposal::factory()->forQuoteRequest($qr)->create();

        $result = $this->repository->getWithQuoteRequests($event->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('quoteRequests'));
        $this->assertTrue($result->relationLoaded('creator'));
        $this->assertTrue($result->quoteRequests->first()->relationLoaded('proposals'));
        $this->assertTrue($result->quoteRequests->first()->relationLoaded('category'));
    }

    public function test_get_with_quote_requests_returns_null_for_nonexistent_id(): void
    {
        $result = $this->repository->getWithQuoteRequests('non-existent-uuid');

        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────
    //  getAllForOrganization
    // ──────────────────────────────────────────────────

    public function test_get_all_for_organization_returns_only_org_events(): void
    {
        $this->createEvent();
        $this->createEvent();

        $otherOrg = $this->createOrganization();
        Event::factory()->forOrganization($otherOrg)->create(['name' => 'Other Org Event']);

        $result = $this->repository->getAllForOrganization($this->organization->id);

        $this->assertCount(2, $result);
    }

    public function test_get_all_for_organization_orders_by_start_date_desc(): void
    {
        $this->createEvent(['name' => 'Early', 'start_date' => '2026-01-01']);
        $this->createEvent(['name' => 'Late', 'start_date' => '2026-12-01']);

        $result = $this->repository->getAllForOrganization($this->organization->id);

        $this->assertEquals('Late', $result[0]->name);
        $this->assertEquals('Early', $result[1]->name);
    }
}
