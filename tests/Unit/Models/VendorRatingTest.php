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
use Tests\TestCase;

class VendorRatingTest extends TestCase
{
    private Organization $organization;

    private User $user;

    private Vendor $vendor;

    private Event $event;

    private Proposal $proposal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->forOrganization($this->organization)->create();
        $this->user->assignRole('admin');
        $this->actingAs($this->user, 'sanctum');

        $this->vendor = Vendor::factory()->create();
        $this->event = Event::factory()->forOrganization($this->organization, $this->user)->create();
        $qr = QuoteRequest::factory()->forEvent($this->event)->create([
            'created_by' => $this->user->id,
        ]);
        $this->proposal = Proposal::factory()->forQuoteRequest($qr, $this->vendor)
            ->contracted()->create();
    }

    // -------------------------------------------------------
    // calculateOverallRating
    // -------------------------------------------------------

    public function test_calculate_overall_rating_averages_four_dimensions(): void
    {
        $rating = new VendorRating([
            'punctuality_rating' => 5,
            'quality_rating' => 4,
            'communication_rating' => 3,
            'price_rating' => 4,
        ]);

        $expected = round((5 + 4 + 3 + 4) / 4, 2);
        $this->assertEquals($expected, $rating->calculateOverallRating());
    }

    public function test_calculate_overall_rating_with_all_fives(): void
    {
        $rating = new VendorRating([
            'punctuality_rating' => 5,
            'quality_rating' => 5,
            'communication_rating' => 5,
            'price_rating' => 5,
        ]);

        $this->assertEquals(5.0, $rating->calculateOverallRating());
    }

    public function test_calculate_overall_rating_with_all_ones(): void
    {
        $rating = new VendorRating([
            'punctuality_rating' => 1,
            'quality_rating' => 1,
            'communication_rating' => 1,
            'price_rating' => 1,
        ]);

        $this->assertEquals(1.0, $rating->calculateOverallRating());
    }

    public function test_overall_rating_auto_calculated_on_create(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create([
            'punctuality_rating' => 5,
            'quality_rating' => 4,
            'communication_rating' => 3,
            'price_rating' => 4,
        ]);

        $expected = round((5 + 4 + 3 + 4) / 4, 2);
        $this->assertEquals($expected, (float) $rating->overall_rating);
    }

    // -------------------------------------------------------
    // dispute
    // -------------------------------------------------------

    public function test_dispute_sets_status_and_reason(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $rating->dispute('This review is unfair');

        $rating->refresh();
        $this->assertEquals('disputed', $rating->status);
        $this->assertEquals('This review is unfair', $rating->dispute_reason);
        $this->assertNotNull($rating->disputed_at);
    }

    public function test_resolve_dispute_keeps_rating_active(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->disputed()->create();

        $resolver = User::factory()->create();
        $rating->resolveDispute($resolver, 'Review is valid', false);

        $rating->refresh();
        $this->assertEquals('active', $rating->status);
        $this->assertEquals($resolver->id, $rating->dispute_resolved_by);
        $this->assertNotNull($rating->resolved_at);
        $this->assertEquals('Review is valid', $rating->resolution_notes);
    }

    public function test_resolve_dispute_removes_rating(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->disputed()->create();

        $resolver = User::factory()->create();
        $rating->resolveDispute($resolver, 'Confirmed unfair review', true);

        $rating->refresh();
        $this->assertEquals('removed', $rating->status);
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function test_active_scope(): void
    {
        VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create(['status' => 'active']);

        $vendor2 = Vendor::factory()->create();
        $qr2 = QuoteRequest::factory()->forEvent($this->event)->create(['created_by' => $this->user->id]);
        $proposal2 = Proposal::factory()->forQuoteRequest($qr2, $vendor2)->contracted()->create();
        VendorRating::factory()->forVendor(
            $vendor2,
            $this->event,
            $proposal2,
            $this->user
        )->disputed()->create();

        $this->assertCount(1, VendorRating::active()->get());
    }

    public function test_disputed_scope(): void
    {
        VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->disputed()->create();

        $this->assertCount(1, VendorRating::disputed()->get());
    }

    public function test_public_visible_scope(): void
    {
        VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $vendor2 = Vendor::factory()->create();
        $qr2 = QuoteRequest::factory()->forEvent($this->event)->create(['created_by' => $this->user->id]);
        $proposal2 = Proposal::factory()->forQuoteRequest($qr2, $vendor2)->contracted()->create();
        VendorRating::factory()->forVendor(
            $vendor2,
            $this->event,
            $proposal2,
            $this->user
        )->create([
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->assertCount(1, VendorRating::publicVisible()->get());
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_vendor(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertInstanceOf(BelongsTo::class, $rating->vendor());
        $this->assertTrue($rating->vendor->is($this->vendor));
    }

    public function test_belongs_to_event(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertInstanceOf(BelongsTo::class, $rating->event());
        $this->assertTrue($rating->event->is($this->event));
    }

    public function test_belongs_to_proposal(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertInstanceOf(BelongsTo::class, $rating->proposal());
        $this->assertTrue($rating->proposal->is($this->proposal));
    }

    public function test_belongs_to_organization(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertInstanceOf(BelongsTo::class, $rating->organization());
        $this->assertTrue($rating->organization->is($this->organization));
    }

    public function test_belongs_to_rater(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertInstanceOf(BelongsTo::class, $rating->rater());
        $this->assertTrue($rating->rater->is($this->user));
    }

    public function test_belongs_to_resolved_by(): void
    {
        $resolver = User::factory()->create();
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create([
            'dispute_resolved_by' => $resolver->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $rating->resolvedBy());
        $this->assertTrue($rating->resolvedBy->is($resolver));
    }

    // -------------------------------------------------------
    // Factory states
    // -------------------------------------------------------

    public function test_factory_default_creates_active_rating(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertEquals('active', $rating->status);
        $this->assertTrue($rating->is_public);
        $this->assertNotNull($rating->punctuality_rating);
        $this->assertNotNull($rating->quality_rating);
        $this->assertNotNull($rating->communication_rating);
        $this->assertNotNull($rating->price_rating);
    }

    public function test_factory_disputed_state(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->disputed()->create();

        $this->assertEquals('disputed', $rating->status);
        $this->assertNotNull($rating->dispute_reason);
        $this->assertNotNull($rating->disputed_at);
    }

    // -------------------------------------------------------
    // Casts
    // -------------------------------------------------------

    public function test_rating_fields_cast_to_integer(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create();

        $this->assertIsInt($rating->punctuality_rating);
        $this->assertIsInt($rating->quality_rating);
        $this->assertIsInt($rating->communication_rating);
        $this->assertIsInt($rating->price_rating);
    }

    public function test_would_hire_again_cast_to_boolean(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create(['would_hire_again' => 1]);

        $this->assertIsBool($rating->would_hire_again);
    }

    // -------------------------------------------------------
    // getWeightedScore
    // -------------------------------------------------------

    public function test_get_weighted_score(): void
    {
        $rating = VendorRating::factory()->forVendor(
            $this->vendor,
            $this->event,
            $this->proposal,
            $this->user
        )->create([
            'punctuality_rating' => 4,
            'quality_rating' => 4,
            'communication_rating' => 4,
            'price_rating' => 4,
            'contract_value' => 10000,
        ]);

        $expectedWeight = log10(10000 + 1);
        $expectedScore = (float) $rating->overall_rating * $expectedWeight;

        $this->assertEqualsWithDelta($expectedScore, $rating->getWeightedScore(), 0.01);
    }
}
