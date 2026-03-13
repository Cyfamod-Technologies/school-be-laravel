<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password123',
            'phone' => fake()->phoneNumber(),
            'whatsapp_number' => fake()->phoneNumber(),
            'bank_account_name' => fake()->name(),
            'bank_account_number' => fake()->numerify('##########'),
            'bank_name' => fake()->company(),
            'company_name' => fake()->optional()->company(),
            'address' => fake()->optional()->address(),
            'status' => 'pending',
        ];
    }

    /**
     * Create an approved agent.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 'admin',
        ]);
    }

    /**
     * Create a pending agent.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Create an inactive (rejected) agent.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'rejection_reason' => 'Did not meet requirements',
        ]);
    }
}
