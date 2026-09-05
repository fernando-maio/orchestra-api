<?php

namespace Tests\Unit\Services;

use App\Contracts\Services\VendorServiceInterface;
use App\Models\Category;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Services\VendorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class VendorServiceTest extends TestCase
{
    private VendorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VendorServiceInterface::class);
    }

    // ──────────────────────────────────────────────────
    //  createWithCategories
    // ──────────────────────────────────────────────────

    public function test_create_with_categories_creates_vendor_and_attaches_categories(): void
    {
        $categories = Category::factory()->count(2)->create();
        $categoryIds = $categories->pluck('id')->toArray();

        $data = [
            'trade_name' => 'Acme Events',
            'legal_name' => 'Acme Events LTDA',
            'cnpj' => '12.345.678/0001-90',
            'email' => 'contact@acme.com',
            'city' => 'Sao Paulo',
            'state' => 'SP',
        ];

        $vendor = $this->service->createWithCategories($data, $categoryIds);

        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals('Acme Events', $vendor->trade_name);
        $this->assertDatabaseHas('vendors', ['cnpj' => '12.345.678/0001-90']);
        $this->assertTrue($vendor->relationLoaded('categories'));
        $this->assertCount(2, $vendor->categories);
    }

    public function test_create_with_categories_works_with_empty_category_ids(): void
    {
        $data = [
            'trade_name' => 'No Category Vendor',
            'cnpj' => '99.999.999/0001-99',
            'email' => 'nope@vendor.com',
        ];

        $vendor = $this->service->createWithCategories($data, []);

        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals('No Category Vendor', $vendor->trade_name);
        $this->assertCount(0, $vendor->categories);
    }

    // ──────────────────────────────────────────────────
    //  updateWithCategories
    // ──────────────────────────────────────────────────

    public function test_update_with_categories_updates_vendor_and_syncs_categories(): void
    {
        $vendor = Vendor::factory()->create(['trade_name' => 'Old Name']);
        $oldCategory = Category::factory()->create();
        $vendor->categories()->attach($oldCategory);

        $newCategories = Category::factory()->count(2)->create();
        $newIds = $newCategories->pluck('id')->toArray();

        $result = $this->service->updateWithCategories($vendor->id, ['trade_name' => 'New Name'], $newIds);

        $this->assertEquals('New Name', $result->trade_name);
        $this->assertCount(2, $result->categories);
        $this->assertFalse($result->categories->pluck('id')->contains($oldCategory->id));
    }

    public function test_update_with_categories_keeps_categories_when_empty_array(): void
    {
        $vendor = Vendor::factory()->create();
        $category = Category::factory()->create();
        $vendor->categories()->attach($category);

        // Empty array means no sync happens (the method checks !empty)
        $result = $this->service->updateWithCategories($vendor->id, ['trade_name' => 'Updated'], []);

        $this->assertEquals('Updated', $result->trade_name);
        // Categories remain since empty array doesn't trigger sync
        $this->assertCount(1, $result->categories);
    }

    // ──────────────────────────────────────────────────
    //  toggleActive
    // ──────────────────────────────────────────────────

    public function test_toggle_active_deactivates_an_active_vendor(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);

        $result = $this->service->toggleActive($vendor->id);

        $this->assertFalse($result->is_active);
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'is_active' => false,
        ]);
    }

    public function test_toggle_active_activates_an_inactive_vendor(): void
    {
        $vendor = Vendor::factory()->inactive()->create();

        $result = $this->service->toggleActive($vendor->id);

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'is_active' => true,
        ]);
    }

    public function test_toggle_active_throws_when_vendor_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->toggleActive('nonexistent-uuid');
    }

    // ──────────────────────────────────────────────────
    //  verify
    // ──────────────────────────────────────────────────

    public function test_verify_sets_is_verified_to_true(): void
    {
        $vendor = Vendor::factory()->create(['is_verified' => false]);

        $result = $this->service->verify($vendor->id);

        $this->assertTrue($result->is_verified);
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'is_verified' => true,
        ]);
    }

    public function test_verify_keeps_already_verified_vendor_as_verified(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        $result = $this->service->verify($vendor->id);

        $this->assertTrue($result->is_verified);
    }

    public function test_verify_throws_when_vendor_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->verify('nonexistent-uuid');
    }

    // ──────────────────────────────────────────────────
    //  getComplianceStatus
    // ──────────────────────────────────────────────────

    public function test_get_compliance_status_returns_expected_structure(): void
    {
        $vendor = Vendor::factory()->create();

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertArrayHasKey('is_compliant', $result);
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('total_documents', $result);

        // Check required document types are present
        $this->assertArrayHasKey('cnpj_card', $result['documents']);
        $this->assertArrayHasKey('alvara', $result['documents']);
        $this->assertArrayHasKey('social_contract', $result['documents']);
    }

    public function test_get_compliance_status_shows_missing_when_no_documents(): void
    {
        $vendor = Vendor::factory()->create();

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertFalse($result['is_compliant']);
        $this->assertEquals(0, $result['total_documents']);
        $this->assertEquals('missing', $result['documents']['cnpj_card']['status']);
        $this->assertEquals('missing', $result['documents']['alvara']['status']);
        $this->assertEquals('missing', $result['documents']['social_contract']['status']);
    }

    public function test_get_compliance_status_shows_document_status_when_uploaded(): void
    {
        $vendor = Vendor::factory()->create();

        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'cnpj_card',
            'expiry_date' => now()->addYear(),
        ]);

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertEquals('verified', $result['documents']['cnpj_card']['status']);
        $this->assertNotNull($result['documents']['cnpj_card']['uploaded_at']);
        $this->assertFalse($result['documents']['cnpj_card']['is_expired']);
    }

    public function test_get_compliance_status_detects_expired_document(): void
    {
        $vendor = Vendor::factory()->create();

        VendorDocument::factory()->forVendor($vendor)->create([
            'type' => 'alvara',
            'status' => 'verified',
            'expiry_date' => now()->subDays(30),
        ]);

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertTrue($result['documents']['alvara']['is_expired']);
    }

    public function test_get_compliance_status_is_compliant_with_all_required_docs_verified(): void
    {
        $vendor = Vendor::factory()->create();

        // Create the two required document types (cnpj_card, alvara) as verified and not expired
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'cnpj_card',
            'expiry_date' => now()->addYear(),
        ]);
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'alvara',
            'expiry_date' => now()->addYear(),
        ]);

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertTrue($result['is_compliant']);
        $this->assertEquals(2, $result['total_documents']);
    }

    public function test_get_compliance_status_not_compliant_when_missing_required_doc(): void
    {
        $vendor = Vendor::factory()->create();

        // Only cnpj_card, missing alvara
        VendorDocument::factory()->forVendor($vendor)->verified()->create([
            'type' => 'cnpj_card',
            'expiry_date' => now()->addYear(),
        ]);

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertFalse($result['is_compliant']);
    }

    public function test_get_compliance_status_counts_all_documents_including_extra(): void
    {
        $vendor = Vendor::factory()->create();

        VendorDocument::factory()->forVendor($vendor)->verified()->create(['type' => 'cnpj_card']);
        VendorDocument::factory()->forVendor($vendor)->verified()->create(['type' => 'alvara']);
        VendorDocument::factory()->forVendor($vendor)->create(['type' => 'insurance']);

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertEquals(3, $result['total_documents']);
    }

    public function test_get_compliance_status_throws_when_vendor_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getComplianceStatus('nonexistent-uuid');
    }

    public function test_get_compliance_status_document_label_translations(): void
    {
        $vendor = Vendor::factory()->create();

        $result = $this->service->getComplianceStatus($vendor->id);

        $this->assertEquals('Cartão CNPJ', $result['documents']['cnpj_card']['label']);
        $this->assertEquals('Alvará de Funcionamento', $result['documents']['alvara']['label']);
        $this->assertEquals('Contrato Social', $result['documents']['social_contract']['label']);
    }

    // ──────────────────────────────────────────────────
    //  registerSelfService
    // ──────────────────────────────────────────────────

    public function test_register_self_service_creates_pending_and_inactive_vendor(): void
    {
        $categoria = Category::factory()->create();

        $vendor = $this->service->registerSelfService([
            'trade_name' => 'Fornecedor Novo',
            'cnpj' => '11.222.333/0001-44',
            'email' => 'novo@example.com',
            'city' => 'São Paulo',
            'state' => 'SP',
        ], [$categoria->id]);

        $this->assertSame('pending', $vendor->approval_status);
        $this->assertSame('self_register', $vendor->source);
        $this->assertFalse((bool) $vendor->is_active);
    }

    public function test_register_self_service_ignores_moderation_fields_from_payload(): void
    {
        // Defesa em profundidade: o FormRequest nao aceita esses campos, mas o
        // service tambem nao pode confiar em quem o chama. Um payload que tente
        // se auto-aprovar precisa ser neutralizado aqui.
        $categoria = Category::factory()->create();

        $vendor = $this->service->registerSelfService([
            'trade_name' => 'Tentativa',
            'cnpj' => '99.888.777/0001-66',
            'email' => 'tentativa@example.com',
            'city' => 'São Paulo',
            'state' => 'SP',
            'approval_status' => 'approved',
            'is_active' => true,
            'source' => 'admin',
        ], [$categoria->id]);

        $this->assertSame('pending', $vendor->approval_status);
        $this->assertFalse((bool) $vendor->is_active);
        $this->assertSame('self_register', $vendor->source);
    }

    public function test_register_self_service_applies_default_radius(): void
    {
        $categoria = Category::factory()->create();

        $vendor = $this->service->registerSelfService([
            'trade_name' => 'Sem Raio',
            'cnpj' => '55.444.333/0001-22',
            'email' => 'semraio@example.com',
            'city' => 'São Paulo',
            'state' => 'SP',
        ], [$categoria->id]);

        $this->assertSame(50, $vendor->service_radius_km);
    }

    public function test_register_self_service_attaches_categories(): void
    {
        $categorias = Category::factory()->count(2)->create();

        $vendor = $this->service->registerSelfService([
            'trade_name' => 'Com Categorias',
            'cnpj' => '77.666.555/0001-88',
            'email' => 'comcat@example.com',
            'city' => 'São Paulo',
            'state' => 'SP',
        ], $categorias->pluck('id')->all());

        $this->assertCount(2, $vendor->categories);
    }

    // ──────────────────────────────────────────────────
    //  cnpjExists / emailExists
    // ──────────────────────────────────────────────────

    public function test_cnpj_exists_detects_registered_vendor(): void
    {
        Vendor::factory()->create(['cnpj' => '12.345.678/0001-99']);

        $this->assertTrue($this->service->cnpjExists('12.345.678/0001-99'));
        $this->assertFalse($this->service->cnpjExists('00.000.000/0000-00'));
    }

    public function test_email_exists_detects_registered_vendor(): void
    {
        Vendor::factory()->create(['email' => 'existe@example.com']);

        $this->assertTrue($this->service->emailExists('existe@example.com'));
        $this->assertFalse($this->service->emailExists('naoexiste@example.com'));
    }

    public function test_soft_deleted_vendor_does_not_block_new_registration(): void
    {
        // Sem isso, um fornecedor removido travaria o CNPJ para sempre.
        $vendor = Vendor::factory()->create(['cnpj' => '33.222.111/0001-00']);
        $vendor->delete();

        $this->assertFalse($this->service->cnpjExists('33.222.111/0001-00'));
    }
}
