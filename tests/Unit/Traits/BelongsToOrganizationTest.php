<?php

namespace Tests\Unit\Traits;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BelongsToOrganizationTest extends TestCase
{
    // -------------------------------------------------------
    // Global scope filters by organization
    // -------------------------------------------------------

    public function test_org_user_sees_only_own_organization_events(): void
    {
        $orgA = Organization::factory()->create();
        $userA = User::factory()->forOrganization($orgA)->create();
        $userA->assignRole('admin');

        $orgB = Organization::factory()->create();
        $userB = User::factory()->forOrganization($orgB)->create();
        $userB->assignRole('admin');

        // Create events without the global scope interfering
        // by using actingAs for the right user at the right time
        $this->actingAs($userA, 'sanctum');
        $eventA = Event::factory()->forOrganization($orgA, $userA)->create(['name' => 'Event A']);

        $this->actingAs($userB, 'sanctum');
        $eventB = Event::factory()->forOrganization($orgB, $userB)->create(['name' => 'Event B']);

        // Now query as user A - should only see Event A
        $this->actingAs($userA, 'sanctum');
        $events = Event::all();

        $this->assertCount(1, $events);
        $this->assertEquals('Event A', $events->first()->name);
    }

    public function test_org_user_cannot_see_other_organization_events(): void
    {
        $orgA = Organization::factory()->create();
        $userA = User::factory()->forOrganization($orgA)->create();
        $userA->assignRole('organizer');

        $orgB = Organization::factory()->create();
        $userB = User::factory()->forOrganization($orgB)->create();
        $userB->assignRole('admin');

        // Create event for org B
        $this->actingAs($userB, 'sanctum');
        Event::factory()->forOrganization($orgB, $userB)->create();

        // Query as user A
        $this->actingAs($userA, 'sanctum');
        $events = Event::all();

        $this->assertCount(0, $events);
    }

    // -------------------------------------------------------
    // Super-admin sees all
    // -------------------------------------------------------

    public function test_super_admin_sees_all_organization_events(): void
    {
        $orgA = Organization::factory()->create();
        $userA = User::factory()->forOrganization($orgA)->create();
        $userA->assignRole('admin');

        $orgB = Organization::factory()->create();
        $userB = User::factory()->forOrganization($orgB)->create();
        $userB->assignRole('admin');

        // Create events for both orgs
        $this->actingAs($userA, 'sanctum');
        Event::factory()->forOrganization($orgA, $userA)->create(['name' => 'Event A']);

        $this->actingAs($userB, 'sanctum');
        Event::factory()->forOrganization($orgB, $userB)->create(['name' => 'Event B']);

        // Super admin should see both
        $superAdmin = $this->actingAsSuperAdmin();
        $events = Event::all();

        $this->assertCount(2, $events);
        $this->assertTrue($events->pluck('name')->contains('Event A'));
        $this->assertTrue($events->pluck('name')->contains('Event B'));
    }

    // -------------------------------------------------------
    // Unauthenticated returns nothing
    // -------------------------------------------------------

    public function test_unauthenticated_user_sees_no_events(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();
        $user->assignRole('admin');

        // Create event while authenticated
        $this->actingAs($user, 'sanctum');
        Event::factory()->forOrganization($org, $user)->create();

        // Log out - ensure Auth::check() returns false
        Auth::forgetGuards();

        $events = Event::all();

        $this->assertCount(0, $events);
    }

    // -------------------------------------------------------
    // Auto-sets organization_id on create
    // -------------------------------------------------------

    public function test_auto_sets_organization_id_on_create(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();
        $user->assignRole('admin');
        $this->actingAs($user, 'sanctum');

        // Create event without explicitly setting organization_id
        $event = Event::create([
            'organization_id' => null, // Will be overwritten by the trait
            'created_by' => $user->id,
            'name' => 'Auto Org Event',
            'description' => 'Test',
            'start_date' => now()->addMonth(),
            'address' => '123 Main St',
            'city' => 'Test City',
            'state' => 'SP',
        ]);

        $this->assertEquals($org->id, $event->organization_id);
    }

    public function test_does_not_overwrite_explicit_organization_id(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $user = $this->createSuperAdmin();
        $this->actingAs($user, 'sanctum');

        $creatorForOrg = User::factory()->forOrganization($orgB)->create();

        // Super admin creates event explicitly for org B
        $event = Event::create([
            'organization_id' => $orgB->id,
            'created_by' => $creatorForOrg->id,
            'name' => 'Explicit Org Event',
            'description' => 'Test',
            'start_date' => now()->addMonth(),
            'address' => '123 Main St',
            'city' => 'Test City',
            'state' => 'SP',
        ]);

        $this->assertEquals($orgB->id, $event->organization_id);
    }

    // -------------------------------------------------------
    // User with no organization sees nothing
    // -------------------------------------------------------

    public function test_authenticated_user_without_organization_sees_nothing(): void
    {
        // Create events first
        $org = Organization::factory()->create();
        $orgUser = User::factory()->forOrganization($org)->create();
        $orgUser->assignRole('admin');
        $this->actingAs($orgUser, 'sanctum');
        Event::factory()->forOrganization($org, $orgUser)->create();

        // User without organization_id
        $noOrgUser = User::factory()->create(['organization_id' => null]);
        $noOrgUser->assignRole('organizer');
        $this->actingAs($noOrgUser, 'sanctum');

        $events = Event::all();

        $this->assertCount(0, $events);
    }
}
