<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteRequest>
 */
class QuoteRequestFactory extends Factory
{
    protected $model = QuoteRequest::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'technical_description' => fake()->paragraphs(2, true),
            'specifications' => fake()->paragraph(),
            'budget_min' => fake()->randomFloat(2, 1000, 10000),
            'budget_max' => fake()->randomFloat(2, 10001, 50000),
            'response_deadline' => now()->addDays(14),
            'delivery_deadline' => now()->addMonths(2),
            'status' => 'open',
            'max_proposals' => fake()->numberBetween(3, 10),
            'is_urgent' => false,
        ];
    }

    public function forEvent(Event $event, ?Category $category = null, ?User $creator = null): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
            'category_id' => $category?->id ?? Category::factory()->create()->id,
            'created_by' => $creator?->id ?? $event->created_by,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_urgent' => true,
            'response_deadline' => now()->addDays(3),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }
}
