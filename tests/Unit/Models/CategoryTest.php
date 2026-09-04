<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    // -------------------------------------------------------
    // Slug auto-generation
    // -------------------------------------------------------

    public function test_slug_is_auto_generated_from_name_on_create(): void
    {
        $category = Category::factory()->create([
            'name' => 'Audio and Sound',
            'slug' => null,
        ]);

        $this->assertEquals('audio-and-sound', $category->slug);
    }

    public function test_slug_is_not_overwritten_if_provided(): void
    {
        $category = Category::factory()->create([
            'name' => 'Audio and Sound',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $category->slug);
    }

    public function test_slug_auto_generation_handles_special_characters(): void
    {
        $category = Category::factory()->create([
            'name' => 'Buffet & Catering',
            'slug' => null,
        ]);

        $this->assertEquals(Str::slug('Buffet & Catering'), $category->slug);
    }

    public function test_slug_is_generated_for_empty_string(): void
    {
        $category = Category::factory()->create([
            'name' => 'Iluminacao',
            'slug' => '',
        ]);

        // empty() returns true for '', so slug should be auto-generated
        $this->assertEquals('iluminacao', $category->slug);
    }

    // -------------------------------------------------------
    // is_active scope
    // -------------------------------------------------------

    public function test_active_scope_returns_only_active_categories(): void
    {
        Category::factory()->create(['is_active' => true, 'name' => 'Active Cat']);
        Category::factory()->create(['is_active' => false, 'name' => 'Inactive Cat']);

        $activeCategories = Category::active()->get();

        $this->assertCount(1, $activeCategories);
        $this->assertEquals('Active Cat', $activeCategories->first()->name);
    }

    public function test_active_scope_returns_empty_when_none_active(): void
    {
        Category::factory()->inactive()->create();
        Category::factory()->inactive()->create();

        $this->assertCount(0, Category::active()->get());
    }

    // -------------------------------------------------------
    // Ordered scope
    // -------------------------------------------------------

    public function test_ordered_scope_sorts_by_sort_order_then_name(): void
    {
        Category::factory()->create(['name' => 'Zebra', 'sort_order' => 1]);
        Category::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
        Category::factory()->create(['name' => 'Beta', 'sort_order' => 0]);

        $ordered = Category::ordered()->get();

        $this->assertEquals('Beta', $ordered[0]->name);
        // sort_order=1 items sorted by name
        $this->assertEquals('Alpha', $ordered[1]->name);
        $this->assertEquals('Zebra', $ordered[2]->name);
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function test_belongs_to_many_vendors(): void
    {
        $category = Category::factory()->create();
        $vendor = Vendor::factory()->create();

        $category->vendors()->attach($vendor->id);

        $this->assertInstanceOf(BelongsToMany::class, $category->vendors());
        $this->assertCount(1, $category->vendors);
        $this->assertTrue($category->vendors->first()->is($vendor));
    }

    public function test_has_many_quote_requests(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();
        $this->actingAs($user, 'sanctum');

        $category = Category::factory()->create();
        $event = Event::factory()->forOrganization($org, $user)->create();
        $quoteRequest = QuoteRequest::factory()->forEvent($event, $category, $user)->create();

        $this->assertInstanceOf(HasMany::class, $category->quoteRequests());
        $this->assertCount(1, $category->quoteRequests);
        $this->assertTrue($category->quoteRequests->first()->is($quoteRequest));
    }

    // -------------------------------------------------------
    // Casts and configuration
    // -------------------------------------------------------

    public function test_is_active_cast_to_boolean(): void
    {
        $category = Category::factory()->create(['is_active' => 1]);

        $this->assertIsBool($category->is_active);
        $this->assertTrue($category->is_active);
    }

    public function test_sort_order_cast_to_integer(): void
    {
        $category = Category::factory()->create(['sort_order' => '5']);

        $this->assertIsInt($category->sort_order);
        $this->assertEquals(5, $category->sort_order);
    }

    public function test_category_uses_soft_deletes(): void
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(new Category)
        );
    }

    // -------------------------------------------------------
    // Factory
    // -------------------------------------------------------

    public function test_factory_creates_active_category_by_default(): void
    {
        $category = Category::factory()->create();

        $this->assertTrue($category->is_active);
        $this->assertNotEmpty($category->name);
        $this->assertNotEmpty($category->slug);
    }

    public function test_factory_inactive_state(): void
    {
        $category = Category::factory()->inactive()->create();

        $this->assertFalse($category->is_active);
    }
}
