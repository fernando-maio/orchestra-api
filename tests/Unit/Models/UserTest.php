<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class UserTest extends TestCase
{
    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->forOrganization($organization)->create();

        $this->assertInstanceOf(BelongsTo::class, $user->organization());
        $this->assertTrue($user->organization->is($organization));
    }

    public function test_has_many_events(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->forOrganization($organization)->create();
        $this->actingAs($user, 'sanctum');

        $event = Event::factory()->forOrganization($organization, $user)->create();

        $this->assertInstanceOf(HasMany::class, $user->events());
        $this->assertCount(1, $user->events);
        $this->assertTrue($user->events->first()->is($event));
    }

    public function test_has_many_quote_requests(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->forOrganization($organization)->create();
        $event = Event::factory()->forOrganization($organization, $user)->create();

        $this->actingAs($user, 'sanctum');

        $quoteRequest = QuoteRequest::factory()->forEvent($event)->create([
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $user->quoteRequests());
        $this->assertCount(1, $user->quoteRequests);
        $this->assertTrue($user->quoteRequests->first()->is($quoteRequest));
    }

    public function test_has_many_proposal_reviews(): void
    {
        $this->assertInstanceOf(HasMany::class, User::factory()->create()->proposalReviews());
    }

    // -------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------

    public function test_is_super_admin_returns_true_when_user_has_super_admin_role(): void
    {
        $user = $this->createSuperAdmin();

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_when_user_does_not_have_role(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_organization_admin_returns_true_when_user_has_admin_role(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createOrgAdmin($organization);

        $this->assertTrue($user->isOrganizationAdmin());
    }

    public function test_is_organization_admin_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isOrganizationAdmin());
    }

    public function test_is_organization_admin_returns_false_for_organizer(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createOrgUser($organization, 'organizer');

        $this->assertFalse($user->isOrganizationAdmin());
    }

    // -------------------------------------------------------
    // canAccessOrganization
    // -------------------------------------------------------

    public function test_super_admin_can_access_any_organization(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $org = Organization::factory()->create();

        $this->assertTrue($superAdmin->canAccessOrganization($org->id));
    }

    public function test_user_can_access_own_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();

        $this->assertTrue($user->canAccessOrganization($org->id));
    }

    public function test_user_cannot_access_other_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $user = User::factory()->forOrganization($orgA)->create();

        $this->assertFalse($user->canAccessOrganization($orgB->id));
    }

    // -------------------------------------------------------
    // Role assignments via TestHelpers
    // -------------------------------------------------------

    public function test_role_assignment_super_admin(): void
    {
        $user = $this->createSuperAdmin();

        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_role_assignment_admin(): void
    {
        $user = $this->createOrgAdmin();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->isOrganizationAdmin());
    }

    public function test_role_assignment_organizer(): void
    {
        $user = $this->createOrgUser(role: 'organizer');

        $this->assertTrue($user->hasRole('organizer'));
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isOrganizationAdmin());
    }

    public function test_role_assignment_viewer(): void
    {
        $user = $this->createOrgUser(role: 'viewer');

        $this->assertTrue($user->hasRole('viewer'));
    }

    // -------------------------------------------------------
    // Model traits and configuration
    // -------------------------------------------------------

    public function test_uses_soft_deletes(): void
    {
        $user = User::factory()->create();

        $this->assertContains(SoftDeletes::class, class_uses_recursive($user));

        $user->delete();

        $this->assertSoftDeleted($user);
        $this->assertNotNull($user->fresh()?->deleted_at ?? User::withTrashed()->find($user->id)->deleted_at);
    }

    public function test_fillable_fields(): void
    {
        $user = new User();

        $this->assertContains('name', $user->getFillable());
        $this->assertContains('email', $user->getFillable());
        $this->assertContains('password', $user->getFillable());
        $this->assertContains('organization_id', $user->getFillable());
        $this->assertContains('is_active', $user->getFillable());
    }

    public function test_hidden_fields(): void
    {
        $user = new User();

        $this->assertContains('password', $user->getHidden());
        $this->assertContains('remember_token', $user->getHidden());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => 1]);

        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
    }

    public function test_factory_creates_active_user_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_factory_inactive_state(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertFalse($user->is_active);
    }
}
