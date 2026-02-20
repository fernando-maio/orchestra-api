<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Vendor;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    // ─── INDEX ─────────────────────────────────────────────────────────

    public function test_index_returns_all_categories(): void
    {
        $this->actingAsSuperAdmin();
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_active_only_filter(): void
    {
        $this->actingAsSuperAdmin();
        Category::factory()->count(2)->create(['is_active' => true]);
        Category::factory()->inactive()->create();

        $response = $this->getJson('/api/categories?active_only=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertUnauthorized();
    }

    // ─── STORE ─────────────────────────────────────────────────────────

    public function test_store_creates_category(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/categories', [
            'name' => 'Catering',
            'description' => 'Food services',
            'icon' => '🍽️',
            'color' => '#FF5733',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Catering');

        $this->assertDatabaseHas('categories', ['name' => 'Catering']);
    }

    public function test_store_validates_required_name(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/categories', [
            'description' => 'Missing name',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_validates_unique_name(): void
    {
        $this->actingAsSuperAdmin();
        Category::factory()->create(['name' => 'Existing']);

        $response = $this->postJson('/api/categories', [
            'name' => 'Existing',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_requires_permission(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'viewer');

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
        ]);

        $response->assertForbidden();
    }

    // ─── SHOW ──────────────────────────────────────────────────────────

    public function test_show_returns_category(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->create();

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/categories/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── UPDATE ────────────────────────────────────────────────────────

    public function test_update_modifies_category(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_update_requires_permission(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'viewer');
        $category = Category::factory()->create();

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Updated',
        ]);

        $response->assertForbidden();
    }

    // ─── DESTROY ───────────────────────────────────────────────────────

    public function test_destroy_deletes_category(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertOk();
    }

    public function test_destroy_blocks_deletion_when_vendors_exist(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->create();
        $vendor = Vendor::factory()->create();
        $vendor->categories()->attach($category->id);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertUnprocessable();
    }

    public function test_destroy_requires_permission(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'viewer');
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertForbidden();
    }

    // ─── TOGGLE ACTIVE ─────────────────────────────────────────────────

    public function test_toggle_active_deactivates_category(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/categories/{$category->id}/toggle-active");

        $response->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
    }

    public function test_toggle_active_activates_category(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->inactive()->create();

        $response = $this->postJson("/api/categories/{$category->id}/toggle-active");

        $response->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => true]);
    }

    public function test_toggle_active_requires_permission(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'viewer');
        $category = Category::factory()->create();

        $response = $this->postJson("/api/categories/{$category->id}/toggle-active");

        $response->assertForbidden();
    }

    // ─── REORDER ───────────────────────────────────────────────────────

    public function test_reorder_updates_sort_order(): void
    {
        $this->actingAsSuperAdmin();
        $categories = Category::factory()->count(3)->create();
        $orderedIds = $categories->pluck('id')->reverse()->values()->toArray();

        $response = $this->postJson('/api/categories/reorder', [
            'ids' => $orderedIds,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $orderedIds[0], 'sort_order' => 1]);
        $this->assertDatabaseHas('categories', ['id' => $orderedIds[1], 'sort_order' => 2]);
        $this->assertDatabaseHas('categories', ['id' => $orderedIds[2], 'sort_order' => 3]);
    }

    public function test_reorder_validates_ids(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/categories/reorder', [
            'ids' => [],
        ]);

        $response->assertUnprocessable();
    }

    public function test_reorder_requires_permission(): void
    {
        $org = $this->createOrganization();
        $this->actingAsOrgUser($org, 'viewer');

        $response = $this->postJson('/api/categories/reorder', [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
        ]);

        $response->assertForbidden();
    }
}
