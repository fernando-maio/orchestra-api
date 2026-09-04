<?php

namespace Tests\Unit\Services;

use App\Contracts\Services\CategoryServiceInterface;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategoryServiceInterface::class);
    }

    // ──────────────────────────────────────────────────
    //  getAllActive
    // ──────────────────────────────────────────────────

    public function test_get_all_active_returns_only_active_categories(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => true]);
        Category::factory()->inactive()->create();

        $result = $this->service->getAllActive();

        $this->assertCount(2, $result);
        $result->each(fn (Category $c) => $this->assertTrue($c->is_active));
    }

    public function test_get_all_active_returns_empty_when_none_exist(): void
    {
        $result = $this->service->getAllActive();

        $this->assertCount(0, $result);
    }

    // ──────────────────────────────────────────────────
    //  getAllOrdered
    // ──────────────────────────────────────────────────

    public function test_get_all_ordered_includes_inactive_categories(): void
    {
        Category::factory()->create(['is_active' => true, 'sort_order' => 1]);
        Category::factory()->inactive()->create(['sort_order' => 2]);

        $result = $this->service->getAllOrdered();

        $this->assertCount(2, $result);
    }

    public function test_get_all_ordered_respects_sort_order(): void
    {
        Category::factory()->create(['sort_order' => 3, 'name' => 'Third']);
        Category::factory()->create(['sort_order' => 1, 'name' => 'First']);
        Category::factory()->create(['sort_order' => 2, 'name' => 'Second']);

        $result = $this->service->getAllOrdered();

        $this->assertEquals('First', $result[0]->name);
        $this->assertEquals('Second', $result[1]->name);
        $this->assertEquals('Third', $result[2]->name);
    }

    // ──────────────────────────────────────────────────
    //  findBySlug
    // ──────────────────────────────────────────────────

    public function test_find_by_slug_returns_category(): void
    {
        $category = Category::factory()->create(['slug' => 'lighting']);

        $result = $this->service->findBySlug('lighting');

        $this->assertNotNull($result);
        $this->assertEquals($category->id, $result->id);
    }

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        $result = $this->service->findBySlug('nonexistent');

        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────
    //  toggleActive
    // ──────────────────────────────────────────────────

    public function test_toggle_active_deactivates_active_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $result = $this->service->toggleActive($category->id);

        $this->assertFalse($result->is_active);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_toggle_active_activates_inactive_category(): void
    {
        $category = Category::factory()->inactive()->create();

        $result = $this->service->toggleActive($category->id);

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_toggle_active_returns_fresh_model(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $result = $this->service->toggleActive($category->id);

        $this->assertInstanceOf(Category::class, $result);
        // The result should reflect the DB state (fresh model)
        $this->assertFalse($result->is_active);
    }

    public function test_toggle_active_throws_when_category_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->toggleActive('nonexistent-uuid');
    }

    // ──────────────────────────────────────────────────
    //  reorder
    // ──────────────────────────────────────────────────

    public function test_reorder_updates_sort_order_for_given_ids(): void
    {
        $catA = Category::factory()->create(['sort_order' => 10, 'name' => 'A']);
        $catB = Category::factory()->create(['sort_order' => 20, 'name' => 'B']);
        $catC = Category::factory()->create(['sort_order' => 30, 'name' => 'C']);

        $result = $this->service->reorder([$catC->id, $catA->id, $catB->id]);

        $this->assertTrue($result);
        $this->assertDatabaseHas('categories', ['id' => $catC->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('categories', ['id' => $catA->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('categories', ['id' => $catB->id, 'sort_order' => 3]);
    }

    public function test_reorder_starts_sort_order_at_one(): void
    {
        $cat = Category::factory()->create(['sort_order' => 99]);

        $this->service->reorder([$cat->id]);

        $this->assertDatabaseHas('categories', ['id' => $cat->id, 'sort_order' => 1]);
    }

    public function test_reorder_returns_true_on_success(): void
    {
        $cat = Category::factory()->create();

        $result = $this->service->reorder([$cat->id]);

        $this->assertTrue($result);
    }

    public function test_reorder_with_empty_array_returns_true(): void
    {
        $result = $this->service->reorder([]);

        $this->assertTrue($result);
    }
}
