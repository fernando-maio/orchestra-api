<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company() . ' LTDA',
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip_code' => fake()->numerify('#####-###'),
            'subscription_status' => 'active',
            'subscription_plan' => 'starter',
            'is_active' => true,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'canceled',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withPlan(string $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_plan' => $plan,
        ]);
    }
}
