<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class QuoteRequestTest extends TestCase
{
    private Organization $organization;
    private User $user;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->forOrganization($this->organization)->create();
        $this->user->assignRole('admin');
        $this->actingAs($this->user, 'sanctum');

        $this->event = Event::factory()->forOrganization($this->organization, $this->user)->create();
    }

    // -------------------------------------------------------
    // isOpen
    // -------------------------------------------------------

    public function test_is_open_returns_true_for_open_status(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'status' => 'open',
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($qr->isOpen());
    }

    public function test_is_open_returns_false_for_closed_status(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->closed()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($qr->isOpen());
    }

    public function test_is_open_returns_false_for_draft_status(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->draft()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($qr->isOpen());
    }

    // -------------------------------------------------------
    // isExpired
    // -------------------------------------------------------

    public function test_is_expired_returns_true_when_deadline_passed(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'response_deadline' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($qr->isExpired());
    }

    public function test_is_expired_returns_false_when_deadline_in_future(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'response_deadline' => now()->addDays(7),
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($qr->isExpired());
    }

    public function test_is_expired_returns_false_when_no_deadline(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'response_deadline' => null,
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($qr->isExpired());
    }

    // -------------------------------------------------------
    // canAcceptProposals
    // -------------------------------------------------------

    public function test_can_accept_proposals_returns_true_when_open_and_not_expired(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'status' => 'open',
            'response_deadline' => now()->addDays(7),
            'max_proposals' => 5,
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($qr->canAcceptProposals());
    }

    public function test_can_accept_proposals_returns_false_when_not_open(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->closed()->create([
            'response_deadline' => now()->addDays(7),
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($qr->canAcceptProposals());
    }

    public function test_can_accept_proposals_returns_false_when_expired(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'status' => 'open',
            'response_deadline' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($qr->canAcceptProposals());
    }

    public function test_can_accept_proposals_returns_false_when_max_reached(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'status' => 'open',
            'response_deadline' => now()->addDays(7),
            'max_proposals' => 1,
            'created_by' => $this->user->id,
        ]);

        Proposal::factory()->forQuoteRequest($qr)->create();

        $this->assertFalse($qr->canAcceptProposals());
    }

    public function test_can_accept_proposals_returns_true_when_max_proposals_is_null(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'status' => 'open',
            'response_deadline' => now()->addDays(7),
            'max_proposals' => null,
            'created_by' => $this->user->id,
        ]);

        // Even with proposals, no max means unlimited
        Proposal::factory()->forQuoteRequest($qr)->create();

        $this->assertTrue($qr->canAcceptProposals());
    }

    // -------------------------------------------------------
    // getProposalsStats
    // -------------------------------------------------------

    public function test_get_proposals_stats_returns_correct_statistics(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);

        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();
        $vendor3 = Vendor::factory()->create();

        Proposal::factory()->forQuoteRequest($qr, $vendor1)->create([
            'status' => 'pending',
            'total_value' => 10000,
        ]);
        Proposal::factory()->forQuoteRequest($qr, $vendor2)->create([
            'status' => 'approved',
            'total_value' => 15000,
        ]);
        Proposal::factory()->forQuoteRequest($qr, $vendor3)->contracted()->create([
            'total_value' => 20000,
            'locked_value' => 20000,
        ]);

        $stats = $qr->getProposalsStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(0, $stats['under_review']);
        $this->assertEquals(1, $stats['approved']);
        $this->assertEquals(1, $stats['contracted']);
        $this->assertEquals(10000, $stats['min_value']);
        $this->assertEquals(20000, $stats['max_value']);
        $this->assertEquals(15000, $stats['avg_value']);
    }

    public function test_get_proposals_stats_returns_empty_stats_with_no_proposals(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);

        $stats = $qr->getProposalsStats();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['pending']);
        $this->assertNull($stats['min_value']);
        $this->assertNull($stats['max_value']);
        $this->assertNull($stats['avg_value']);
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function test_open_scope(): void
    {
        QuoteRequest::factory()->forEvent($this->event)->create([
            'status' => 'open',
            'created_by' => $this->user->id,
        ]);
        QuoteRequest::factory()->forEvent($this->event)->closed()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertCount(1, QuoteRequest::open()->get());
    }

    public function test_urgent_scope(): void
    {
        QuoteRequest::factory()->forEvent($this->event)->urgent()->create([
            'created_by' => $this->user->id,
        ]);
        QuoteRequest::factory()->forEvent($this->event)->create([
            'is_urgent' => false,
            'created_by' => $this->user->id,
        ]);

        $urgent = QuoteRequest::urgent()->get();

        $this->assertCount(1, $urgent);
        $this->assertTrue($urgent->first()->is_urgent);
    }

    public function test_expiring_soon_scope(): void
    {
        QuoteRequest::factory()->forEvent($this->event)->create([
            'response_deadline' => now()->addDays(2),
            'created_by' => $this->user->id,
            'title' => 'Expiring Soon',
        ]);
        QuoteRequest::factory()->forEvent($this->event)->create([
            'response_deadline' => now()->addDays(10),
            'created_by' => $this->user->id,
            'title' => 'Not Expiring Soon',
        ]);
        QuoteRequest::factory()->forEvent($this->event)->create([
            'response_deadline' => now()->subDay(),
            'created_by' => $this->user->id,
            'title' => 'Already Expired',
        ]);

        $expiringSoon = QuoteRequest::expiringSoon(3)->get();

        $this->assertCount(1, $expiringSoon);
        $this->assertEquals('Expiring Soon', $expiringSoon->first()->title);
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_event(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $qr->event());
        $this->assertTrue($qr->event->is($this->event));
    }

    public function test_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $qr = QuoteRequest::factory()->forEvent($this->event, $category)->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $qr->category());
        $this->assertTrue($qr->category->is($category));
    }

    public function test_belongs_to_creator(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $qr->creator());
        $this->assertTrue($qr->creator->is($this->user));
    }

    public function test_has_many_proposals(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);
        Proposal::factory()->forQuoteRequest($qr)->create();

        $this->assertInstanceOf(HasMany::class, $qr->proposals());
        $this->assertCount(1, $qr->proposals);
    }

    public function test_belongs_to_many_vendors(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(BelongsToMany::class, $qr->vendors());
    }

    // -------------------------------------------------------
    // Model configuration
    // -------------------------------------------------------

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(new QuoteRequest()));
    }

    public function test_is_urgent_cast_to_boolean(): void
    {
        $qr = QuoteRequest::factory()->forEvent($this->event)->urgent()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertIsBool($qr->is_urgent);
        $this->assertTrue($qr->is_urgent);
    }

    public function test_requirements_cast_to_array(): void
    {
        $reqs = ['insurance' => true, 'certification' => 'ISO9001'];
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
            'requirements' => $reqs,
        ]);

        $this->assertIsArray($qr->requirements);
        $this->assertEquals('ISO9001', $qr->requirements['certification']);
    }
}
