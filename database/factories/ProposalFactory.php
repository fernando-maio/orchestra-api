<?php

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\QuoteRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 5000, 100000);

        return [
            'total_value' => $total,
            'labor_value' => round($total * 0.4, 2),
            'material_value' => round($total * 0.35, 2),
            'transport_value' => round($total * 0.1, 2),
            'taxes_value' => round($total * 0.15, 2),
            'currency' => 'BRL',
            'payment_terms' => fake()->randomElement(['à vista', '30 dias', '30/60 dias', '30/60/90 dias']),
            'payment_days' => fake()->randomElement([0, 30, 60]),
            'description' => fake()->paragraph(),
            'status' => 'pending',
            'valid_until' => now()->addDays(30),
        ];
    }

    public function forQuoteRequest(QuoteRequest $quoteRequest, ?Vendor $vendor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quote_request_id' => $quoteRequest->id,
            'vendor_id' => $vendor?->id ?? Vendor::factory()->create()->id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_at' => now(),
            'review_notes' => fake()->sentence(),
        ]);
    }

    public function contracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'contracted',
            'locked_value' => $attributes['total_value'] ?? fake()->randomFloat(2, 5000, 100000),
            'locked_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => now()->subDays(5),
        ]);
    }
}
