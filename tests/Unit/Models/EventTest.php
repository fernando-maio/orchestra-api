<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class EventTest extends TestCase
{
    private Organization $organization;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->forOrganization($this->organization)->create();
        $this->user->assignRole('admin');
        $this->actingAs($this->user, 'sanctum');
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function test_active_scope_returns_only_active_events(): void
    {
        Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create(['name' => 'Active Event']);
        Event::factory()->forOrganization($this->organization, $this->user)
            ->create(['name' => 'Draft Event', 'status' => 'draft']);

        $active = Event::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active Event', $active->first()->name);
    }

    public function test_upcoming_scope_returns_future_events(): void
    {
        Event::factory()->forOrganization($this->organization, $this->user)
            ->upcoming()->create(['name' => 'Future Event']);
        Event::factory()->forOrganization($this->organization, $this->user)
            ->past()->create(['name' => 'Past Event']);

        $upcoming = Event::upcoming()->get();

        $this->assertCount(1, $upcoming);
        $this->assertEquals('Future Event', $upcoming->first()->name);
    }

    public function test_past_scope_returns_past_events(): void
    {
        Event::factory()->forOrganization($this->organization, $this->user)
            ->upcoming()->create(['name' => 'Future Event']);
        Event::factory()->forOrganization($this->organization, $this->user)
            ->past()->create(['name' => 'Past Event']);

        $past = Event::past()->get();

        $this->assertCount(1, $past);
        $this->assertEquals('Past Event', $past->first()->name);
    }

    public function test_draft_scope_returns_draft_events(): void
    {
        Event::factory()->forOrganization($this->organization, $this->user)
            ->create(['name' => 'Draft Event', 'status' => 'draft']);
        Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create(['name' => 'Active Event']);

        $drafts = Event::draft()->get();

        $this->assertCount(1, $drafts);
        $this->assertEquals('Draft Event', $drafts->first()->name);
    }

    // -------------------------------------------------------
    // getContractedBudget
    // -------------------------------------------------------

    public function test_get_contracted_budget_sums_contracted_proposals(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create();

        $qr = QuoteRequest::factory()->forEvent($event)->create([
            'created_by' => $this->user->id,
        ]);

        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();

        Proposal::factory()->forQuoteRequest($qr, $vendor1)->contracted()->create([
            'locked_value' => 10000.00,
        ]);
        Proposal::factory()->forQuoteRequest($qr, $vendor2)->create([
            'status' => 'pending',
            'total_value' => 5000.00,
        ]);

        $this->assertEquals(10000.00, $event->getContractedBudget());
    }

    public function test_get_contracted_budget_returns_zero_when_no_contracted_proposals(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create();

        $this->assertEquals(0, $event->getContractedBudget());
    }

    // -------------------------------------------------------
    // getBudgetProgress
    // -------------------------------------------------------

    public function test_get_budget_progress_returns_percentage(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create(['estimated_budget' => 100000.00]);

        $qr = QuoteRequest::factory()->forEvent($event)->create([
            'created_by' => $this->user->id,
        ]);

        Proposal::factory()->forQuoteRequest($qr)->contracted()->create([
            'locked_value' => 25000.00,
        ]);

        $this->assertEquals(25.0, $event->getBudgetProgress());
    }

    public function test_get_budget_progress_returns_zero_when_no_budget(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create(['estimated_budget' => 0]);

        $this->assertEquals(0, $event->getBudgetProgress());
    }

    public function test_get_budget_progress_returns_zero_when_null_budget(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create(['estimated_budget' => null]);

        $this->assertEquals(0, $event->getBudgetProgress());
    }

    // -------------------------------------------------------
    // canBeEdited
    // -------------------------------------------------------

    public function test_can_be_edited_returns_true_for_draft(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->create(['status' => 'draft']);

        $this->assertTrue($event->canBeEdited());
    }

    public function test_can_be_edited_returns_true_for_active(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create();

        $this->assertTrue($event->canBeEdited());
    }

    public function test_can_be_edited_returns_false_for_completed(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->completed()->create();

        $this->assertFalse($event->canBeEdited());
    }

    public function test_can_be_edited_returns_false_for_canceled(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->canceled()->create();

        $this->assertFalse($event->canBeEdited());
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_organization(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();

        $this->assertInstanceOf(BelongsTo::class, $event->organization());
        $this->assertTrue($event->organization->is($this->organization));
    }

    public function test_belongs_to_creator(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();

        $this->assertInstanceOf(BelongsTo::class, $event->creator());
        $this->assertTrue($event->creator->is($this->user));
    }

    public function test_has_many_quote_requests(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();
        QuoteRequest::factory()->forEvent($event)->create(['created_by' => $this->user->id]);

        $this->assertInstanceOf(HasMany::class, $event->quoteRequests());
        $this->assertCount(1, $event->quoteRequests);
    }

    public function test_has_many_through_proposals(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();
        $qr = QuoteRequest::factory()->forEvent($event)->create(['created_by' => $this->user->id]);
        Proposal::factory()->forQuoteRequest($qr)->create();

        $this->assertInstanceOf(HasManyThrough::class, $event->proposals());
        $this->assertCount(1, $event->proposals);
    }

    public function test_has_many_ratings(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();

        $this->assertInstanceOf(HasMany::class, $event->ratings());
    }

    // -------------------------------------------------------
    // Model traits and configuration
    // -------------------------------------------------------

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(new Event()));
    }

    public function test_date_casts(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->start_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->end_date);
    }

    public function test_required_categories_cast_to_array(): void
    {
        $categories = ['cat-1', 'cat-2', 'cat-3'];
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create([
            'required_categories' => $categories,
        ]);

        $this->assertIsArray($event->required_categories);
        $this->assertCount(3, $event->required_categories);
    }

    // -------------------------------------------------------
    // Factory states
    // -------------------------------------------------------

    public function test_factory_default_creates_draft_event(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)->create();

        $this->assertEquals('draft', $event->status);
    }

    public function test_factory_active_state(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->active()->create();

        $this->assertEquals('active', $event->status);
    }

    public function test_factory_completed_state(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->completed()->create();

        $this->assertEquals('completed', $event->status);
        $this->assertTrue($event->start_date->isPast());
    }

    public function test_factory_canceled_state(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->canceled()->create();

        $this->assertEquals('canceled', $event->status);
    }

    public function test_factory_upcoming_state(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->upcoming()->create();

        $this->assertEquals('active', $event->status);
        $this->assertTrue($event->start_date->isFuture());
    }

    public function test_factory_past_state(): void
    {
        $event = Event::factory()->forOrganization($this->organization, $this->user)
            ->past()->create();

        $this->assertTrue($event->start_date->isPast());
    }
}
