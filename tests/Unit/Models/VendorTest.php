<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorRating;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class VendorTest extends TestCase
{
    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function test_active_scope_returns_only_active_vendors(): void
    {
        Vendor::factory()->create(['is_active' => true, 'trade_name' => 'Active Vendor']);
        Vendor::factory()->create(['is_active' => false, 'trade_name' => 'Inactive Vendor']);

        $active = Vendor::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active Vendor', $active->first()->trade_name);
    }

    public function test_verified_scope_returns_only_verified_vendors(): void
    {
        Vendor::factory()->verified()->create(['trade_name' => 'Verified']);
        Vendor::factory()->create(['is_verified' => false, 'trade_name' => 'Not Verified']);

        $verified = Vendor::verified()->get();

        $this->assertCount(1, $verified);
        $this->assertEquals('Verified', $verified->first()->trade_name);
    }

    public function test_approved_scope_returns_only_approved_vendors(): void
    {
        Vendor::factory()->create(['approval_status' => 'approved', 'trade_name' => 'Approved']);
        Vendor::factory()->create(['approval_status' => 'pending', 'trade_name' => 'Pending']);
        Vendor::factory()->create(['approval_status' => 'rejected', 'trade_name' => 'Rejected']);

        $approved = Vendor::approved()->get();

        $this->assertCount(1, $approved);
        $this->assertEquals('Approved', $approved->first()->trade_name);
    }

    public function test_pending_scope_returns_only_pending_vendors(): void
    {
        Vendor::factory()->create(['approval_status' => 'pending', 'trade_name' => 'Pending']);
        Vendor::factory()->create(['approval_status' => 'approved', 'trade_name' => 'Approved']);

        $pending = Vendor::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('Pending', $pending->first()->trade_name);
    }

    public function test_public_visible_scope_returns_active_and_approved(): void
    {
        Vendor::factory()->create(['is_active' => true, 'approval_status' => 'approved', 'trade_name' => 'Visible']);
        Vendor::factory()->create(['is_active' => false, 'approval_status' => 'approved', 'trade_name' => 'Inactive approved']);
        Vendor::factory()->create(['is_active' => true, 'approval_status' => 'pending', 'trade_name' => 'Active pending']);
        Vendor::factory()->create(['is_active' => false, 'approval_status' => 'rejected', 'trade_name' => 'Inactive rejected']);

        $visible = Vendor::publicVisible()->get();

        $this->assertCount(1, $visible);
        $this->assertEquals('Visible', $visible->first()->trade_name);
    }

    public function test_accepts_urgent_scope(): void
    {
        Vendor::factory()->create(['accepts_urgent' => true, 'trade_name' => 'Urgent']);
        Vendor::factory()->create(['accepts_urgent' => false, 'trade_name' => 'Not Urgent']);

        $urgent = Vendor::acceptsUrgent()->get();

        $this->assertCount(1, $urgent);
        $this->assertEquals('Urgent', $urgent->first()->trade_name);
    }

    public function test_featured_scope_returns_featured_and_premium_with_valid_subscription(): void
    {
        Vendor::factory()->create([
            'subscription_tier' => 'featured',
            'subscription_expires_at' => null,
            'trade_name' => 'Featured no expiry',
        ]);
        Vendor::factory()->create([
            'subscription_tier' => 'premium',
            'subscription_expires_at' => now()->addMonth(),
            'trade_name' => 'Premium valid',
        ]);
        Vendor::factory()->create([
            'subscription_tier' => 'featured',
            'subscription_expires_at' => now()->subDay(),
            'trade_name' => 'Featured expired',
        ]);
        Vendor::factory()->create([
            'subscription_tier' => 'free',
            'trade_name' => 'Free',
        ]);

        $featured = Vendor::featured()->get();

        $this->assertCount(2, $featured);
        $this->assertTrue($featured->pluck('trade_name')->contains('Featured no expiry'));
        $this->assertTrue($featured->pluck('trade_name')->contains('Premium valid'));
    }

    // -------------------------------------------------------
    // hasValidCompliance
    // -------------------------------------------------------

    public function test_has_valid_compliance_returns_true_with_all_required_docs(): void
    {
        $vendor = Vendor::factory()->create();

        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'cnpj_card',
            'expiry_date' => now()->addMonths(6),
        ]);
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'alvara',
            'expiry_date' => null, // No expiry
        ]);

        $this->assertTrue($vendor->hasValidCompliance());
    }

    public function test_has_valid_compliance_returns_false_when_missing_doc(): void
    {
        $vendor = Vendor::factory()->create();

        // Only cnpj_card, missing alvara
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'cnpj_card',
            'expiry_date' => now()->addMonths(6),
        ]);

        $this->assertFalse($vendor->hasValidCompliance());
    }

    public function test_has_valid_compliance_returns_false_when_doc_not_verified(): void
    {
        $vendor = Vendor::factory()->create();

        VendorDocument::factory()->forVendor($vendor)->create([
            'type' => 'cnpj_card',
            'status' => 'pending',
        ]);
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'alvara',
        ]);

        $this->assertFalse($vendor->hasValidCompliance());
    }

    public function test_has_valid_compliance_returns_false_when_doc_expired(): void
    {
        $vendor = Vendor::factory()->create();

        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'cnpj_card',
            'expiry_date' => now()->subDay(),
        ]);
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'alvara',
        ]);

        $this->assertFalse($vendor->hasValidCompliance());
    }

    // -------------------------------------------------------
    // approve / reject / isApproved / isPending
    // -------------------------------------------------------

    public function test_approve_sets_status_and_approver(): void
    {
        $vendor = Vendor::factory()->create(['approval_status' => 'pending']);
        $approver = User::factory()->create();

        $vendor->approve($approver);

        $vendor->refresh();
        $this->assertEquals('approved', $vendor->approval_status);
        $this->assertEquals($approver->id, $vendor->approved_by);
        $this->assertNotNull($vendor->approved_at);
    }

    public function test_reject_sets_status_reason_and_approver(): void
    {
        $vendor = Vendor::factory()->create(['approval_status' => 'pending']);
        $approver = User::factory()->create();

        $vendor->reject($approver, 'Incomplete documentation');

        $vendor->refresh();
        $this->assertEquals('rejected', $vendor->approval_status);
        $this->assertEquals('Incomplete documentation', $vendor->rejection_reason);
        $this->assertEquals($approver->id, $vendor->approved_by);
        $this->assertNotNull($vendor->approved_at);
    }

    public function test_is_approved_returns_true_for_approved_vendor(): void
    {
        $vendor = Vendor::factory()->create(['approval_status' => 'approved']);

        $this->assertTrue($vendor->isApproved());
    }

    public function test_is_approved_returns_false_for_pending_vendor(): void
    {
        $vendor = Vendor::factory()->create(['approval_status' => 'pending']);

        $this->assertFalse($vendor->isApproved());
    }

    public function test_is_pending_returns_true_for_pending_vendor(): void
    {
        $vendor = Vendor::factory()->create(['approval_status' => 'pending']);

        $this->assertTrue($vendor->isPending());
    }

    public function test_is_pending_returns_false_for_approved_vendor(): void
    {
        $vendor = Vendor::factory()->create(['approval_status' => 'approved']);

        $this->assertFalse($vendor->isPending());
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_organization(): void
    {
        $org = Organization::factory()->create();
        $vendor = Vendor::factory()->forOrganization($org)->create();

        $this->assertInstanceOf(BelongsTo::class, $vendor->organization());
        $this->assertTrue($vendor->organization->is($org));
    }

    public function test_belongs_to_many_categories(): void
    {
        $vendor = Vendor::factory()->create();
        $category = Category::factory()->create();

        $vendor->categories()->attach($category->id);

        $this->assertInstanceOf(BelongsToMany::class, $vendor->categories());
        $this->assertCount(1, $vendor->categories);
        $this->assertTrue($vendor->categories->first()->is($category));
    }

    public function test_has_many_proposals(): void
    {
        $this->assertInstanceOf(HasMany::class, Vendor::factory()->create()->proposals());
    }

    public function test_has_many_documents(): void
    {
        $vendor = Vendor::factory()->create();
        VendorDocument::factory()->forVendor($vendor)->create();

        $this->assertInstanceOf(HasMany::class, $vendor->documents());
        $this->assertCount(1, $vendor->documents);
    }

    public function test_has_many_ratings(): void
    {
        $this->assertInstanceOf(HasMany::class, Vendor::factory()->create()->ratings());
    }

    public function test_approved_by_relationship(): void
    {
        $approver = User::factory()->create();
        $vendor = Vendor::factory()->create(['approved_by' => $approver->id]);

        $this->assertInstanceOf(BelongsTo::class, $vendor->approvedBy());
        $this->assertTrue($vendor->approvedBy->is($approver));
    }

    public function test_invited_by_relationship(): void
    {
        $inviter = User::factory()->create();
        $vendor = Vendor::factory()->create(['invited_by' => $inviter->id]);

        $this->assertInstanceOf(BelongsTo::class, $vendor->invitedBy());
        $this->assertTrue($vendor->invitedBy->is($inviter));
    }

    // -------------------------------------------------------
    // Model traits and configuration
    // -------------------------------------------------------

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(new Vendor()));
    }

    public function test_boolean_casts(): void
    {
        $vendor = Vendor::factory()->create([
            'accepts_urgent' => 1,
            'is_verified' => 1,
            'is_active' => 1,
            'is_local_business' => 1,
            'is_sustainable' => 1,
            'is_minority_owned' => 1,
        ]);

        $this->assertIsBool($vendor->accepts_urgent);
        $this->assertIsBool($vendor->is_verified);
        $this->assertIsBool($vendor->is_active);
        $this->assertIsBool($vendor->is_local_business);
        $this->assertIsBool($vendor->is_sustainable);
        $this->assertIsBool($vendor->is_minority_owned);
    }

    // -------------------------------------------------------
    // Factory states
    // -------------------------------------------------------

    public function test_factory_default_state(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertTrue($vendor->is_active);
        $this->assertFalse($vendor->is_verified);
        $this->assertEquals('approved', $vendor->approval_status);
        $this->assertEquals('admin', $vendor->source);
    }

    public function test_factory_verified_state(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        $this->assertTrue($vendor->is_verified);
    }

    public function test_factory_inactive_state(): void
    {
        $vendor = Vendor::factory()->inactive()->create();

        $this->assertFalse($vendor->is_active);
    }

    public function test_factory_premium_state(): void
    {
        $vendor = Vendor::factory()->premium()->create();

        $this->assertEquals('premium', $vendor->subscription_tier);
    }

    // -------------------------------------------------------
    // ESG scope
    // -------------------------------------------------------

    public function test_esg_filtered_scope(): void
    {
        Vendor::factory()->create([
            'is_local_business' => true,
            'is_sustainable' => true,
            'is_minority_owned' => false,
            'trade_name' => 'Local Sustainable',
        ]);
        Vendor::factory()->create([
            'is_local_business' => false,
            'is_sustainable' => false,
            'is_minority_owned' => true,
            'trade_name' => 'Minority',
        ]);

        $localSustainable = Vendor::esgFiltered(['local' => true, 'sustainable' => true])->get();
        $this->assertCount(1, $localSustainable);
        $this->assertEquals('Local Sustainable', $localSustainable->first()->trade_name);

        $minority = Vendor::esgFiltered(['minority_owned' => true])->get();
        $this->assertCount(1, $minority);
        $this->assertEquals('Minority', $minority->first()->trade_name);
    }
}
