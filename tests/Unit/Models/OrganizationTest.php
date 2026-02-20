<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_has_many_users(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();

        $this->assertInstanceOf(HasMany::class, $org->users());
        $this->assertCount(1, $org->users);
        $this->assertTrue($org->users->first()->is($user));
    }

    public function test_has_many_events(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();

        $this->actingAs($user, 'sanctum');

        $event = Event::factory()->forOrganization($org, $user)->create();

        $this->assertInstanceOf(HasMany::class, $org->events());
        $this->assertCount(1, $org->events);
        $this->assertTrue($org->events->first()->is($event));
    }

    public function test_has_many_vendors(): void
    {
        $org = Organization::factory()->create();
        $vendor = Vendor::factory()->forOrganization($org)->create();

        $this->assertInstanceOf(HasMany::class, $org->vendors());
        $this->assertCount(1, $org->vendors);
        $this->assertTrue($org->vendors->first()->is($vendor));
    }

    // -------------------------------------------------------
    // isSubscriptionActive
    // -------------------------------------------------------

    public function test_is_subscription_active_returns_true_for_active_status(): void
    {
        $org = Organization::factory()->create([
            'subscription_status' => 'active',
        ]);

        $this->assertTrue($org->isSubscriptionActive());
    }

    public function test_is_subscription_active_returns_true_for_trial_with_future_end(): void
    {
        $org = Organization::factory()->trial()->create();

        $this->assertTrue($org->isSubscriptionActive());
        $this->assertEquals('trial', $org->subscription_status);
        $this->assertTrue($org->subscription_ends_at->isFuture());
    }

    public function test_is_subscription_active_returns_false_for_trial_with_past_end(): void
    {
        $org = Organization::factory()->create([
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($org->isSubscriptionActive());
    }

    public function test_is_subscription_active_returns_false_for_canceled(): void
    {
        $org = Organization::factory()->canceled()->create();

        $this->assertFalse($org->isSubscriptionActive());
    }

    public function test_is_subscription_active_returns_false_for_past_due(): void
    {
        $org = Organization::factory()->create([
            'subscription_status' => 'past_due',
        ]);

        $this->assertFalse($org->isSubscriptionActive());
    }

    public function test_is_subscription_active_returns_false_for_trial_without_end_date(): void
    {
        $org = Organization::factory()->create([
            'subscription_status' => 'trial',
            'subscription_ends_at' => null,
        ]);

        $this->assertFalse($org->isSubscriptionActive());
    }

    // -------------------------------------------------------
    // hasFeature
    // -------------------------------------------------------

    public function test_has_feature_basic_plan(): void
    {
        $org = Organization::factory()->withPlan('basic')->create();

        $this->assertTrue($org->hasFeature('events'));
        $this->assertTrue($org->hasFeature('vendors'));
        $this->assertTrue($org->hasFeature('rfp'));
        $this->assertFalse($org->hasFeature('compliance'));
        $this->assertFalse($org->hasFeature('reports'));
        $this->assertFalse($org->hasFeature('api'));
    }

    public function test_has_feature_professional_plan(): void
    {
        $org = Organization::factory()->withPlan('professional')->create();

        $this->assertTrue($org->hasFeature('events'));
        $this->assertTrue($org->hasFeature('compliance'));
        $this->assertTrue($org->hasFeature('reports'));
        $this->assertFalse($org->hasFeature('api'));
        $this->assertFalse($org->hasFeature('white_label'));
    }

    public function test_has_feature_enterprise_plan(): void
    {
        $org = Organization::factory()->withPlan('enterprise')->create();

        $this->assertTrue($org->hasFeature('events'));
        $this->assertTrue($org->hasFeature('compliance'));
        $this->assertTrue($org->hasFeature('reports'));
        $this->assertTrue($org->hasFeature('api'));
        $this->assertTrue($org->hasFeature('webhooks'));
        $this->assertTrue($org->hasFeature('white_label'));
    }

    public function test_has_feature_unknown_plan_returns_false(): void
    {
        $org = Organization::factory()->withPlan('nonexistent')->create();

        $this->assertFalse($org->hasFeature('events'));
    }

    // -------------------------------------------------------
    // Factory states
    // -------------------------------------------------------

    public function test_factory_default_creates_active_organization(): void
    {
        $org = Organization::factory()->create();

        $this->assertTrue($org->is_active);
        $this->assertEquals('active', $org->subscription_status);
        $this->assertEquals('starter', $org->subscription_plan);
    }

    public function test_factory_trial_state(): void
    {
        $org = Organization::factory()->trial()->create();

        $this->assertEquals('trial', $org->subscription_status);
        $this->assertNotNull($org->subscription_ends_at);
        $this->assertTrue($org->subscription_ends_at->isFuture());
    }

    public function test_factory_canceled_state(): void
    {
        $org = Organization::factory()->canceled()->create();

        $this->assertEquals('canceled', $org->subscription_status);
    }

    public function test_factory_inactive_state(): void
    {
        $org = Organization::factory()->inactive()->create();

        $this->assertFalse($org->is_active);
    }

    // -------------------------------------------------------
    // Model traits and configuration
    // -------------------------------------------------------

    public function test_uses_soft_deletes(): void
    {
        $org = Organization::factory()->create();

        $this->assertContains(SoftDeletes::class, class_uses_recursive($org));

        $org->delete();

        $this->assertSoftDeleted($org);
    }

    public function test_settings_cast_to_array(): void
    {
        $settings = ['theme' => 'dark', 'notifications' => true];
        $org = Organization::factory()->create(['settings' => $settings]);

        $this->assertIsArray($org->settings);
        $this->assertEquals('dark', $org->settings['theme']);
        $this->assertTrue($org->settings['notifications']);
    }

    public function test_white_label_enabled_cast_to_boolean(): void
    {
        $org = Organization::factory()->create(['white_label_enabled' => 1]);

        $this->assertIsBool($org->white_label_enabled);
        $this->assertTrue($org->white_label_enabled);
    }

    public function test_subscription_ends_at_cast_to_datetime(): void
    {
        $org = Organization::factory()->trial()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $org->subscription_ends_at);
    }
}
