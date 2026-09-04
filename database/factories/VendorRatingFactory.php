<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorRating>
 */
class VendorRatingFactory extends Factory
{
    protected $model = VendorRating::class;

    public function definition(): array
    {
        $ratings = [
            fake()->numberBetween(1, 5),
            fake()->numberBetween(1, 5),
            fake()->numberBetween(1, 5),
            fake()->numberBetween(1, 5),
        ];

        return [
            'punctuality_rating' => $ratings[0],
            'quality_rating' => $ratings[1],
            'communication_rating' => $ratings[2],
            'price_rating' => $ratings[3],
            'overall_rating' => round(array_sum($ratings) / 4, 2),
            'comment' => fake()->paragraph(),
            'would_hire_again' => fake()->boolean(80),
            'is_public' => true,
            'contract_value' => fake()->randomFloat(2, 5000, 100000),
            'status' => 'active',
        ];
    }

    public function forVendor(Vendor $vendor, Event $event, Proposal $proposal, User $ratedBy): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => $vendor->id,
            'event_id' => $event->id,
            'proposal_id' => $proposal->id,
            'rated_by' => $ratedBy->id,
            'organization_id' => $ratedBy->organization_id,
        ]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disputed',
            'dispute_reason' => fake()->paragraph(),
            'disputed_at' => now(),
        ]);
    }
}
