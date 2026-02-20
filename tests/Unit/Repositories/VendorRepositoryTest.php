<?php

namespace Tests\Unit\Repositories;

use App\Models\Category;
use App\Models\Vendor;
use App\Repositories\VendorRepository;
use Tests\TestCase;

class VendorRepositoryTest extends TestCase
{
    private VendorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VendorRepository(new Vendor());
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – search
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_returns_all_when_no_filters(): void
    {
        Vendor::factory()->count(3)->create();

        $result = $this->repository->paginateWithFilters([]);

        $this->assertEquals(3, $result->total());
    }

    public function test_paginate_with_filters_search_by_trade_name(): void
    {
        Vendor::factory()->create(['trade_name' => 'Acme Photography']);
        Vendor::factory()->create(['trade_name' => 'Beta Catering']);

        $result = $this->repository->paginateWithFilters(['search' => 'Acme']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Acme Photography', $result->items()[0]->trade_name);
    }

    public function test_paginate_with_filters_search_by_legal_name(): void
    {
        Vendor::factory()->create(['legal_name' => 'Acme Corp LTDA']);
        Vendor::factory()->create(['legal_name' => 'Beta Corp LTDA']);

        $result = $this->repository->paginateWithFilters(['search' => 'Acme Corp']);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_search_by_cnpj(): void
    {
        Vendor::factory()->create(['cnpj' => '12.345.678/0001-90']);
        Vendor::factory()->create(['cnpj' => '99.888.777/0001-11']);

        $result = $this->repository->paginateWithFilters(['search' => '12.345']);

        $this->assertEquals(1, $result->total());
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – category
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_by_category_id(): void
    {
        $category = Category::factory()->create();
        $vendorInCategory = Vendor::factory()->create();
        $vendorInCategory->categories()->attach($category);
        Vendor::factory()->create(); // no category

        $result = $this->repository->paginateWithFilters(['category_id' => $category->id]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($vendorInCategory->id, $result->items()[0]->id);
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – location
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_by_city(): void
    {
        Vendor::factory()->create(['city' => 'Sao Paulo']);
        Vendor::factory()->create(['city' => 'Rio de Janeiro']);

        $result = $this->repository->paginateWithFilters(['city' => 'Sao Paulo']);

        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_with_filters_by_state(): void
    {
        Vendor::factory()->create(['state' => 'SP']);
        Vendor::factory()->create(['state' => 'RJ']);

        $result = $this->repository->paginateWithFilters(['state' => 'SP']);

        $this->assertEquals(1, $result->total());
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – boolean flags
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_by_is_active_true(): void
    {
        Vendor::factory()->create(['is_active' => true]);
        Vendor::factory()->inactive()->create();

        $result = $this->repository->paginateWithFilters(['is_active' => true]);

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->items()[0]->is_active);
    }

    public function test_paginate_with_filters_by_is_active_false(): void
    {
        Vendor::factory()->create(['is_active' => true]);
        Vendor::factory()->inactive()->create();

        $result = $this->repository->paginateWithFilters(['is_active' => false]);

        $this->assertEquals(1, $result->total());
        $this->assertFalse($result->items()[0]->is_active);
    }

    public function test_paginate_with_filters_by_is_verified(): void
    {
        Vendor::factory()->verified()->create();
        Vendor::factory()->create(['is_verified' => false]);

        $result = $this->repository->paginateWithFilters(['is_verified' => true]);

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->items()[0]->is_verified);
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – marketplace filters
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_by_approval_status(): void
    {
        Vendor::factory()->create(['approval_status' => 'approved']);
        Vendor::factory()->create(['approval_status' => 'pending']);

        $result = $this->repository->paginateWithFilters(['approval_status' => 'approved']);

        $this->assertEquals(1, $result->total());
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – sorting
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_sorts_by_allowed_column(): void
    {
        Vendor::factory()->create(['trade_name' => 'Zeta']);
        Vendor::factory()->create(['trade_name' => 'Alpha']);

        $result = $this->repository->paginateWithFilters(['sort_by' => 'trade_name', 'sort_dir' => 'asc']);

        $this->assertEquals('Alpha', $result->items()[0]->trade_name);
        $this->assertEquals('Zeta', $result->items()[1]->trade_name);
    }

    public function test_paginate_with_filters_sorts_desc(): void
    {
        Vendor::factory()->create(['trade_name' => 'Alpha']);
        Vendor::factory()->create(['trade_name' => 'Zeta']);

        $result = $this->repository->paginateWithFilters(['sort_by' => 'trade_name', 'sort_dir' => 'desc']);

        $this->assertEquals('Zeta', $result->items()[0]->trade_name);
        $this->assertEquals('Alpha', $result->items()[1]->trade_name);
    }

    public function test_paginate_with_filters_ignores_disallowed_sort_column(): void
    {
        Vendor::factory()->create(['trade_name' => 'Zeta']);
        Vendor::factory()->create(['trade_name' => 'Alpha']);

        // 'email' is not in the whitelist, should fall back to 'trade_name'
        $result = $this->repository->paginateWithFilters(['sort_by' => 'email']);

        $this->assertEquals('Alpha', $result->items()[0]->trade_name);
    }

    public function test_paginate_with_filters_defaults_to_trade_name_asc(): void
    {
        Vendor::factory()->create(['trade_name' => 'Zeta']);
        Vendor::factory()->create(['trade_name' => 'Alpha']);

        $result = $this->repository->paginateWithFilters([]);

        $this->assertEquals('Alpha', $result->items()[0]->trade_name);
        $this->assertEquals('Zeta', $result->items()[1]->trade_name);
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – pagination
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_respects_per_page(): void
    {
        Vendor::factory()->count(10)->create();

        $result = $this->repository->paginateWithFilters([], 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(10, $result->total());
    }

    // ──────────────────────────────────────────────────
    //  paginateWithFilters – combined filters
    // ──────────────────────────────────────────────────

    public function test_paginate_with_filters_combines_multiple_filters(): void
    {
        Vendor::factory()->create(['trade_name' => 'Active SP', 'city' => 'Sao Paulo', 'is_active' => true]);
        Vendor::factory()->create(['trade_name' => 'Active RJ', 'city' => 'Rio de Janeiro', 'is_active' => true]);
        Vendor::factory()->inactive()->create(['trade_name' => 'Inactive SP', 'city' => 'Sao Paulo']);

        $result = $this->repository->paginateWithFilters([
            'city' => 'Sao Paulo',
            'is_active' => true,
        ]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Active SP', $result->items()[0]->trade_name);
    }

    // ──────────────────────────────────────────────────
    //  findByCnpj
    // ──────────────────────────────────────────────────

    public function test_find_by_cnpj_returns_vendor(): void
    {
        $vendor = Vendor::factory()->create(['cnpj' => '12.345.678/0001-90']);

        $result = $this->repository->findByCnpj('12.345.678/0001-90');

        $this->assertNotNull($result);
        $this->assertEquals($vendor->id, $result->id);
    }

    public function test_find_by_cnpj_returns_null_when_not_found(): void
    {
        Vendor::factory()->create(['cnpj' => '12.345.678/0001-90']);

        $result = $this->repository->findByCnpj('00.000.000/0000-00');

        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────
    //  findByCategory
    // ──────────────────────────────────────────────────

    public function test_find_by_category_returns_active_vendors_in_category(): void
    {
        $category = Category::factory()->create();

        $activeVendor = Vendor::factory()->create(['is_active' => true]);
        $activeVendor->categories()->attach($category);

        $inactiveVendor = Vendor::factory()->inactive()->create();
        $inactiveVendor->categories()->attach($category);

        $result = $this->repository->findByCategory($category->id);

        $this->assertCount(1, $result);
        $this->assertEquals($activeVendor->id, $result->first()->id);
    }

    public function test_find_by_category_returns_empty_for_category_with_no_vendors(): void
    {
        $category = Category::factory()->create();

        $result = $this->repository->findByCategory($category->id);

        $this->assertCount(0, $result);
    }

    // ──────────────────────────────────────────────────
    //  syncCategories
    // ──────────────────────────────────────────────────

    public function test_sync_categories_attaches_categories_to_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $categories = Category::factory()->count(3)->create();
        $categoryIds = $categories->pluck('id')->toArray();

        $this->repository->syncCategories($vendor->id, $categoryIds);

        $vendor->refresh();
        $this->assertCount(3, $vendor->categories);
    }

    public function test_sync_categories_replaces_existing_categories(): void
    {
        $vendor = Vendor::factory()->create();
        $oldCategories = Category::factory()->count(2)->create();
        $vendor->categories()->attach($oldCategories->pluck('id'));

        $newCategories = Category::factory()->count(2)->create();
        $newIds = $newCategories->pluck('id')->toArray();

        $this->repository->syncCategories($vendor->id, $newIds);

        $vendor->refresh();
        $this->assertCount(2, $vendor->categories);
        $this->assertEquals(
            collect($newIds)->sort()->values()->toArray(),
            $vendor->categories->pluck('id')->sort()->values()->toArray()
        );
    }

    // ──────────────────────────────────────────────────
    //  getAllActive
    // ──────────────────────────────────────────────────

    public function test_get_all_active_returns_only_active_vendors(): void
    {
        Vendor::factory()->create(['is_active' => true]);
        Vendor::factory()->create(['is_active' => true]);
        Vendor::factory()->inactive()->create();

        $result = $this->repository->getAllActive();

        $this->assertCount(2, $result);
        $result->each(fn (Vendor $v) => $this->assertTrue($v->is_active));
    }

    public function test_get_all_active_eager_loads_categories(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $category = Category::factory()->create();
        $vendor->categories()->attach($category);

        $result = $this->repository->getAllActive();

        $this->assertTrue($result->first()->relationLoaded('categories'));
        $this->assertCount(1, $result->first()->categories);
    }
}
