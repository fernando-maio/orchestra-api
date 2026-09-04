<?php

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\ProposalHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalHistory>
 */
class ProposalHistoryFactory extends Factory
{
    protected $model = ProposalHistory::class;

    public function definition(): array
    {
        return [
            'action' => fake()->randomElement(['created', 'approved', 'rejected', 'contracted']),
            'new_values' => ['status' => 'approved'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function forProposal(Proposal $proposal, ?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'proposal_id' => $proposal->id,
            'user_id' => $user?->id,
        ]);
    }
}
