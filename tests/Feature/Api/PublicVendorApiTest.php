<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Vendor;
use Tests\TestCase;

class PublicVendorApiTest extends TestCase
{
    // ─── CATEGORIES ────────────────────────────────────────────────────

    public function test_categories_returns_active_categories(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);
        Category::factory()->inactive()->create();

        $response = $this->getJson('/api/public/vendors/categories');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_categories_does_not_require_authentication(): void
    {
        Category::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/public/vendors/categories');

        $response->assertOk();
    }

    // ─── REGISTER ──────────────────────────────────────────────────────

    public function test_register_creates_vendor_with_pending_status(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'My Vendor',
            'cnpj' => '12.345.678/0001-90',
            'email' => 'vendor@example.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '(11) 99999-0000',
            'category_ids' => [$category->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.trade_name', 'My Vendor');

        $this->assertDatabaseHas('vendors', [
            'trade_name' => 'My Vendor',
            'approval_status' => 'pending',
            'source' => 'self_register',
            'is_active' => false,
        ]);
    }

    public function test_register_does_not_require_authentication(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Public Vendor',
            'cnpj' => '98.765.432/0001-10',
            'email' => 'public@example.com',
            'city' => 'Rio de Janeiro',
            'state' => 'RJ',
            'contact_name' => 'Jane Smith',
            'contact_email' => 'jane@example.com',
            'contact_phone' => '(21) 88888-0000',
            'category_ids' => [$category->id],
        ]);

        $response->assertCreated();
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/public/vendors/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'trade_name',
                'cnpj',
                'email',
                'city',
                'state',
                'contact_name',
                'contact_email',
                'contact_phone',
                'category_ids',
            ]);
    }

    public function test_register_validates_unique_cnpj(): void
    {
        $category = Category::factory()->create();
        Vendor::factory()->create(['cnpj' => '12.345.678/0001-90']);

        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Duplicate CNPJ Vendor',
            'cnpj' => '12.345.678/0001-90',
            'email' => 'unique@example.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '(11) 99999-0000',
            'category_ids' => [$category->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cnpj');
    }

    public function test_register_validates_unique_email(): void
    {
        $category = Category::factory()->create();
        Vendor::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Duplicate Email Vendor',
            'cnpj' => '11.222.333/0001-44',
            'email' => 'taken@example.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '(11) 99999-0000',
            'category_ids' => [$category->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_validates_category_ids_exist(): void
    {
        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Test Vendor',
            'cnpj' => '55.666.777/0001-88',
            'email' => 'test@example.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '(11) 99999-0000',
            'category_ids' => ['00000000-0000-0000-0000-000000000000'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids.0');
    }

    public function test_register_requires_at_least_one_category(): void
    {
        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Test Vendor',
            'cnpj' => '55.666.777/0001-88',
            'email' => 'test@example.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '(11) 99999-0000',
            'category_ids' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids');
    }

    public function test_register_attaches_categories_to_vendor(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Multi Category Vendor',
            'cnpj' => '44.555.666/0001-77',
            'email' => 'multi@example.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '(11) 99999-0000',
            'category_ids' => [$category1->id, $category2->id],
        ]);

        $response->assertCreated();

        $vendor = Vendor::where('email', 'multi@example.com')->first();
        $this->assertNotNull($vendor);
        $this->assertCount(2, $vendor->categories);
    }

    public function test_register_accepts_optional_fields(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/public/vendors/register', [
            'trade_name' => 'Full Vendor',
            'legal_name' => 'Full Vendor LTDA',
            'cnpj' => '77.888.999/0001-00',
            'email' => 'full@example.com',
            'phone' => '(11) 3333-4444',
            'whatsapp' => '(11) 99999-8888',
            'address' => 'Rua Completa, 100',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'zip_code' => '01001-000',
            'service_radius_km' => 100,
            'description' => 'Full description of vendor services',
            'accepts_urgent' => true,
            'is_local_business' => true,
            'is_sustainable' => false,
            'is_minority_owned' => true,
            'contact_name' => 'Full Contact',
            'contact_email' => 'contact@full.com',
            'contact_phone' => '(11) 99999-7777',
            'category_ids' => [$category->id],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vendors', [
            'legal_name' => 'Full Vendor LTDA',
            'service_radius_km' => 100,
            'accepts_urgent' => true,
            'is_minority_owned' => true,
        ]);
    }

    // ─── CHECK CNPJ ────────────────────────────────────────────────────

    public function test_check_cnpj_returns_false_when_available(): void
    {
        $response = $this->postJson('/api/public/vendors/check-cnpj', [
            'cnpj' => '99.999.999/0001-99',
        ]);

        $response->assertOk()
            ->assertJsonPath('exists', false)
            ->assertJsonPath('message', null);
    }

    public function test_check_cnpj_returns_true_when_taken(): void
    {
        Vendor::factory()->create(['cnpj' => '12.345.678/0001-90']);

        $response = $this->postJson('/api/public/vendors/check-cnpj', [
            'cnpj' => '12.345.678/0001-90',
        ]);

        $response->assertOk()
            ->assertJsonPath('exists', true);
    }

    public function test_check_cnpj_requires_cnpj_field(): void
    {
        $response = $this->postJson('/api/public/vendors/check-cnpj', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cnpj');
    }

    public function test_check_cnpj_does_not_require_authentication(): void
    {
        $response = $this->postJson('/api/public/vendors/check-cnpj', [
            'cnpj' => '11.111.111/0001-11',
        ]);

        $response->assertOk();
    }

    // ─── CHECK EMAIL ───────────────────────────────────────────────────

    public function test_check_email_returns_false_when_available(): void
    {
        $response = $this->postJson('/api/public/vendors/check-email', [
            'email' => 'available@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('exists', false)
            ->assertJsonPath('message', null);
    }

    public function test_check_email_returns_true_when_taken(): void
    {
        Vendor::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/public/vendors/check-email', [
            'email' => 'taken@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('exists', true);
    }

    public function test_check_email_requires_email_field(): void
    {
        $response = $this->postJson('/api/public/vendors/check-email', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_check_email_validates_email_format(): void
    {
        $response = $this->postJson('/api/public/vendors/check-email', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_check_email_does_not_require_authentication(): void
    {
        $response = $this->postJson('/api/public/vendors/check-email', [
            'email' => 'test@example.com',
        ]);

        $response->assertOk();
    }
}
