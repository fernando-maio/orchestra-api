<?php

namespace Tests\Unit\Traits;

use App\Models\Category;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Tests\TestCase;

class HasUuidTest extends TestCase
{
    // -------------------------------------------------------
    // UUID auto-generation
    // -------------------------------------------------------

    public function test_uuid_is_auto_generated_on_create(): void
    {
        $organization = Organization::factory()->create();

        $this->assertNotNull($organization->id);
        $this->assertTrue(Str::isUuid($organization->id));
    }

    public function test_uuid_is_generated_for_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertTrue(Str::isUuid($user->id));
    }

    public function test_uuid_is_generated_for_category(): void
    {
        $category = Category::factory()->create();

        $this->assertNotNull($category->id);
        $this->assertTrue(Str::isUuid($category->id));
    }

    public function test_uuid_is_generated_for_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertNotNull($vendor->id);
        $this->assertTrue(Str::isUuid($vendor->id));
    }

    public function test_custom_uuid_is_not_overwritten(): void
    {
        $customUuid = Str::uuid()->toString();
        $organization = Organization::factory()->create(['id' => $customUuid]);

        $this->assertEquals($customUuid, $organization->id);
    }

    // -------------------------------------------------------
    // Key type is string
    // -------------------------------------------------------

    public function test_key_type_is_string(): void
    {
        $organization = new Organization();

        $this->assertEquals('string', $organization->getKeyType());
    }

    public function test_key_type_is_string_for_user(): void
    {
        $user = new User();

        $this->assertEquals('string', $user->getKeyType());
    }

    // -------------------------------------------------------
    // Not incrementing
    // -------------------------------------------------------

    public function test_not_incrementing(): void
    {
        $organization = new Organization();

        $this->assertFalse($organization->getIncrementing());
    }

    public function test_not_incrementing_for_user(): void
    {
        $user = new User();

        $this->assertFalse($user->getIncrementing());
    }

    public function test_not_incrementing_for_vendor(): void
    {
        $vendor = new Vendor();

        $this->assertFalse($vendor->getIncrementing());
    }

    // -------------------------------------------------------
    // UUIDs are unique across creates
    // -------------------------------------------------------

    public function test_each_model_gets_unique_uuid(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $this->assertNotEquals($org1->id, $org2->id);
        $this->assertTrue(Str::isUuid($org1->id));
        $this->assertTrue(Str::isUuid($org2->id));
    }
}
