<?php

namespace Tests\Unit\Repositories;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Tests\TestCase;

class CategoryRepositoryTest extends TestCase
{
    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CategoryRepository(new Category());
    }

    public function test_get_all_active_returns_only_active_categories(): void
    {
        Category::factory()->create(['is_active' => true, 'sort_order' => 2, 'name' => 'Bravo']);
        Category::factory()->create(['is_active' => true, 'sort_order' => 1, 'name' => 'Alpha']);
        Category::factory()->inactive()->create(['sort_order' => 0, 'name' => 'Charlie']);

        $result = $this->repository->getAllActive();

        $this->assertCount(2, $result);
        $result->each(fn (Category $c) => $this->assertTrue($c->is_active));
    }

    public function test_get_all_active_returns_ordered_by_sort_order_then_name(): void
    {
        Category::factory()->create(['is_active' => true, 'sort_order' => 2, 'name' => 'Bravo']);
        Category::factory()->create(['is_active' => true, 'sort_order' => 1, 'name' => 'Alpha']);
        Category::factory()->create(['is_active' => true, 'sort_order' => 1, 'name' => 'Able']);

        $result = $this->repository->getAllActive();

        $this->assertCount(3, $result);
        $this->assertEquals('Able', $result[0]->name);
        $this->assertEquals('Alpha', $result[1]->name);
        $this->assertEquals('Bravo', $result[2]->name);
    }

    public function test_get_all_active_returns_empty_collection_when_none_active(): void
    {
        Category::factory()->inactive()->count(3)->create();

        $result = $this->repository->getAllActive();

        $this->assertCount(0, $result);
    }

    public function test_get_all_ordered_returns_all_categories_including_inactive(): void
    {
        Category::factory()->create(['is_active' => true, 'sort_order' => 2]);
        Category::factory()->inactive()->create(['sort_order' => 1]);
        Category::factory()->create(['is_active' => true, 'sort_order' => 3]);

        $result = $this->repository->getAllOrdered();

        $this->assertCount(3, $result);
    }

    public function test_get_all_ordered_respects_sort_order_then_name(): void
    {
        Category::factory()->create(['sort_order' => 3, 'name' => 'Charlie']);
        Category::factory()->create(['sort_order' => 1, 'name' => 'Alpha']);
        Category::factory()->create(['sort_order' => 2, 'name' => 'Bravo']);

        $result = $this->repository->getAllOrdered();

        $this->assertEquals('Alpha', $result[0]->name);
        $this->assertEquals('Bravo', $result[1]->name);
        $this->assertEquals('Charlie', $result[2]->name);
    }

    public function test_find_by_slug_returns_category_when_found(): void
    {
        $category = Category::factory()->create(['slug' => 'photography']);

        $result = $this->repository->findBySlug('photography');

        $this->assertNotNull($result);
        $this->assertEquals($category->id, $result->id);
    }

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        Category::factory()->create(['slug' => 'catering']);

        $result = $this->repository->findBySlug('nonexistent');

        $this->assertNull($result);
    }

    public function test_base_repository_find_or_fail_works(): void
    {
        $category = Category::factory()->create();

        $result = $this->repository->findOrFail($category->id);

        $this->assertEquals($category->id, $result->id);
    }

    public function test_base_repository_create_works(): void
    {
        $data = [
            'name' => 'Photography',
            'slug' => 'photography',
            'description' => 'Photo services',
            'is_active' => true,
            'sort_order' => 1,
        ];

        $result = $this->repository->create($data);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Photography', $result->name);
        $this->assertDatabaseHas('categories', ['slug' => 'photography']);
    }
}
