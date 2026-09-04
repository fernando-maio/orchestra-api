<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'trade_name' => fake()->company(),
            'legal_name' => fake()->company().' LTDA',
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip_code' => fake()->numerify('#####-###'),
            'description' => fake()->paragraph(),
            'is_active' => true,
            'is_verified' => false,
            'average_rating' => 0,
            'total_ratings' => 0,
            'service_radius_km' => 50,
            'approval_status' => 'approved',
            'source' => 'admin',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'pending',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'rejected',
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withCoordinates(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => fake()->latitude(-23.8, -23.4),
            'longitude' => fake()->longitude(-46.9, -46.5),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_tier' => 'featured',
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_tier' => 'premium',
        ]);
    }
}
