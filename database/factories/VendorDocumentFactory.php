<?php

namespace Database\Factories;

use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorDocument>
 */
class VendorDocumentFactory extends Factory
{
    protected $model = VendorDocument::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['cnpj_card', 'alvara', 'insurance', 'negative_certificate', 'technical_cert']),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'file_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'issue_date' => now()->subMonths(6),
            'expiry_date' => now()->addMonths(6),
            'status' => 'pending',
        ];
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => $vendor->id,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expiry_date' => now()->subDays(30),
        ]);
    }
}
