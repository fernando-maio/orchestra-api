<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\ProposalHistory;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    private Organization $organization;
    private User $user;
    private Event $event;
    private QuoteRequest $quoteRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->forOrganization($this->organization)->create();
        $this->user->assignRole('admin');
        $this->actingAs($this->user, 'sanctum');

        $this->event = Event::factory()->forOrganization($this->organization, $this->user)->create();
        $this->quoteRequest = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);
    }

    // -------------------------------------------------------
    // isLocked
    // -------------------------------------------------------

    public function test_is_locked_returns_true_when_locked_at_is_set(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->contracted()->create();

        $this->assertTrue($proposal->isLocked());
    }

    public function test_is_locked_returns_false_when_locked_at_is_null(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'locked_at' => null,
        ]);

        $this->assertFalse($proposal->isLocked());
    }

    // -------------------------------------------------------
    // isExpired
    // -------------------------------------------------------

    public function test_is_expired_returns_true_when_valid_until_is_past(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->expired()->create();

        $this->assertTrue($proposal->isExpired());
    }

    public function test_is_expired_returns_false_when_valid_until_is_future(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'valid_until' => now()->addDays(30),
        ]);

        $this->assertFalse($proposal->isExpired());
    }

    public function test_is_expired_returns_false_when_valid_until_is_null(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'valid_until' => null,
        ]);

        $this->assertFalse($proposal->isExpired());
    }

    // -------------------------------------------------------
    // canBeEdited
    // -------------------------------------------------------

    public function test_can_be_edited_returns_true_for_pending_unlocked(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'pending',
            'locked_at' => null,
        ]);

        $this->assertTrue($proposal->canBeEdited());
    }

    public function test_can_be_edited_returns_true_for_draft_unlocked(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->draft()->create([
            'locked_at' => null,
        ]);

        $this->assertTrue($proposal->canBeEdited());
    }

    public function test_can_be_edited_returns_false_when_locked(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'pending',
            'locked_at' => now(),
            'locked_value' => 10000,
        ]);

        $this->assertFalse($proposal->canBeEdited());
    }

    public function test_can_be_edited_returns_false_for_approved(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->approved()->create([
            'locked_at' => null,
        ]);

        $this->assertFalse($proposal->canBeEdited());
    }

    public function test_can_be_edited_returns_false_for_contracted(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->contracted()->create();

        $this->assertFalse($proposal->canBeEdited());
    }

    public function test_can_be_edited_returns_false_for_rejected(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->rejected()->create([
            'locked_at' => null,
        ]);

        $this->assertFalse($proposal->canBeEdited());
    }

    // -------------------------------------------------------
    // lock
    // -------------------------------------------------------

    public function test_lock_sets_locked_value_and_locked_at(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'total_value' => 15000.00,
            'locked_at' => null,
            'locked_value' => null,
        ]);

        $proposal->lock();

        $proposal->refresh();
        $this->assertNotNull($proposal->locked_at);
        $this->assertEquals(15000.00, (float) $proposal->locked_value);
    }

    public function test_lock_does_not_overwrite_existing_lock(): void
    {
        $originalLockedAt = now()->subHour();
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'total_value' => 20000.00,
            'locked_at' => $originalLockedAt,
            'locked_value' => 10000.00,
        ]);

        $proposal->lock();

        $proposal->refresh();
        // Should still be the original locked value, not the new total_value
        $this->assertEquals(10000.00, (float) $proposal->locked_value);
    }

    // -------------------------------------------------------
    // approve
    // -------------------------------------------------------

    public function test_approve_sets_status_and_reviewer(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'pending',
        ]);
        $reviewer = User::factory()->create();

        $proposal->approve($reviewer, 'Looks good');

        $proposal->refresh();
        $this->assertEquals('approved', $proposal->status);
        $this->assertEquals($reviewer->id, $proposal->reviewed_by);
        $this->assertNotNull($proposal->reviewed_at);
        $this->assertEquals('Looks good', $proposal->review_notes);
    }

    public function test_approve_creates_history_record(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'pending',
        ]);
        $reviewer = User::factory()->create();

        $proposal->approve($reviewer);

        $this->assertCount(1, $proposal->history);
        $this->assertEquals('approved', $proposal->history->first()->action);
        $this->assertEquals($reviewer->id, $proposal->history->first()->user_id);
    }

    // -------------------------------------------------------
    // reject
    // -------------------------------------------------------

    public function test_reject_sets_status_and_reviewer(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'pending',
        ]);
        $reviewer = User::factory()->create();

        $proposal->reject($reviewer, 'Too expensive');

        $proposal->refresh();
        $this->assertEquals('rejected', $proposal->status);
        $this->assertEquals($reviewer->id, $proposal->reviewed_by);
        $this->assertNotNull($proposal->reviewed_at);
        $this->assertEquals('Too expensive', $proposal->review_notes);
    }

    public function test_reject_creates_history_record(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'pending',
        ]);
        $reviewer = User::factory()->create();

        $proposal->reject($reviewer, 'Not suitable');

        $this->assertCount(1, $proposal->history);
        $this->assertEquals('rejected', $proposal->history->first()->action);
    }

    // -------------------------------------------------------
    // contract
    // -------------------------------------------------------

    public function test_contract_locks_and_sets_contracted_status(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'approved',
            'total_value' => 25000.00,
            'locked_at' => null,
        ]);

        $proposal->contract($this->user);

        $proposal->refresh();
        $this->assertEquals('contracted', $proposal->status);
        $this->assertTrue($proposal->isLocked());
        $this->assertEquals(25000.00, (float) $proposal->locked_value);
    }

    public function test_contract_creates_history_record(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'status' => 'approved',
            'total_value' => 25000.00,
            'locked_at' => null,
        ]);

        $proposal->contract($this->user);

        $this->assertCount(1, $proposal->history);
        $this->assertEquals('contracted', $proposal->history->first()->action);
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function test_pending_scope(): void
    {
        Proposal::factory()->forQuoteRequest($this->quoteRequest)->create(['status' => 'pending']);
        $vendor2 = Vendor::factory()->create();
        $qr2 = QuoteRequest::factory()->forEvent($this->event)->create(['created_by' => $this->user->id]);
        Proposal::factory()->forQuoteRequest($qr2, $vendor2)->approved()->create();

        $this->assertCount(1, Proposal::pending()->get());
    }

    public function test_approved_scope(): void
    {
        Proposal::factory()->forQuoteRequest($this->quoteRequest)->approved()->create();
        $qr2 = QuoteRequest::factory()->forEvent($this->event)->create(['created_by' => $this->user->id]);
        Proposal::factory()->forQuoteRequest($qr2)->create(['status' => 'pending']);

        $this->assertCount(1, Proposal::approved()->get());
    }

    public function test_contracted_scope(): void
    {
        Proposal::factory()->forQuoteRequest($this->quoteRequest)->contracted()->create();
        $qr2 = QuoteRequest::factory()->forEvent($this->event)->create(['created_by' => $this->user->id]);
        Proposal::factory()->forQuoteRequest($qr2)->create(['status' => 'pending']);

        $this->assertCount(1, Proposal::contracted()->get());
    }

    public function test_valid_scope_includes_null_valid_until(): void
    {
        Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'valid_until' => null,
        ]);

        $this->assertCount(1, Proposal::valid()->get());
    }

    public function test_valid_scope_includes_future_valid_until(): void
    {
        Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'valid_until' => now()->addDays(10),
        ]);

        $this->assertCount(1, Proposal::valid()->get());
    }

    public function test_valid_scope_excludes_expired(): void
    {
        Proposal::factory()->forQuoteRequest($this->quoteRequest)->expired()->create();

        $this->assertCount(0, Proposal::valid()->get());
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_quote_request(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create();

        $this->assertInstanceOf(BelongsTo::class, $proposal->quoteRequest());
        $this->assertTrue($proposal->quoteRequest->is($this->quoteRequest));
    }

    public function test_belongs_to_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest, $vendor)->create();

        $this->assertInstanceOf(BelongsTo::class, $proposal->vendor());
        $this->assertTrue($proposal->vendor->is($vendor));
    }

    public function test_belongs_to_reviewer(): void
    {
        $reviewer = User::factory()->create();
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'reviewed_by' => $reviewer->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $proposal->reviewer());
        $this->assertTrue($proposal->reviewer->is($reviewer));
    }

    public function test_has_many_history(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create();

        $this->assertInstanceOf(HasMany::class, $proposal->history());
    }

    // -------------------------------------------------------
    // Model configuration
    // -------------------------------------------------------

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(new Proposal()));
    }

    public function test_value_casts_to_decimal(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'total_value' => 15000.50,
        ]);

        // decimal:2 casts return strings in some contexts, but the value should be correct
        $this->assertEquals(15000.50, (float) $proposal->total_value);
    }

    public function test_items_cast_to_array(): void
    {
        $items = [
            ['name' => 'Item 1', 'qty' => 10, 'price' => 100],
            ['name' => 'Item 2', 'qty' => 5, 'price' => 200],
        ];
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create([
            'items' => $items,
        ]);

        $this->assertIsArray($proposal->items);
        $this->assertCount(2, $proposal->items);
    }

    // -------------------------------------------------------
    // Factory states
    // -------------------------------------------------------

    public function test_factory_default_creates_pending_proposal(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->create();

        $this->assertEquals('pending', $proposal->status);
    }

    public function test_factory_approved_state(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->approved()->create();

        $this->assertEquals('approved', $proposal->status);
        $this->assertNotNull($proposal->reviewed_at);
    }

    public function test_factory_rejected_state(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->rejected()->create();

        $this->assertEquals('rejected', $proposal->status);
        $this->assertNotNull($proposal->reviewed_at);
        $this->assertNotNull($proposal->review_notes);
    }

    public function test_factory_contracted_state(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->contracted()->create();

        $this->assertEquals('contracted', $proposal->status);
        $this->assertNotNull($proposal->locked_at);
        $this->assertNotNull($proposal->locked_value);
    }

    public function test_factory_expired_state(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->expired()->create();

        $this->assertTrue($proposal->valid_until->isPast());
        $this->assertTrue($proposal->isExpired());
    }

    public function test_factory_draft_state(): void
    {
        $proposal = Proposal::factory()->forQuoteRequest($this->quoteRequest)->draft()->create();

        $this->assertEquals('draft', $proposal->status);
    }
}
