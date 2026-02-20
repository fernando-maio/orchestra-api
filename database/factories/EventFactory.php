<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+6 months');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(1, 5) . ' days');

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'venue_name' => fake()->company() . ' Center',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip_code' => fake()->numerify('#####-###'),
            'estimated_budget' => fake()->randomFloat(2, 5000, 500000),
            'expected_attendees' => fake()->numberBetween(50, 5000),
            'status' => 'draft',
            'currency' => 'BRL',
        ];
    }

    public function forOrganization(Organization $organization, ?User $creator = null): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
            'created_by' => $creator?->id ?? User::factory()->forOrganization($organization)->create()->id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonths(2)->addDays(3),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => now()->addDays(fake()->numberBetween(3, 30)),
            'end_date' => now()->addDays(fake()->numberBetween(31, 60)),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonths(3),
            'end_date' => now()->subMonths(3)->addDays(2),
        ]);
    }
}
