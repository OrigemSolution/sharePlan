<?php

namespace Database\Factories;

use App\Models\Slot;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Slot>
 */
class SlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'user_id' => User::factory(),
            'current_members' => fake()->numberBetween(1, 5),
            'duration' => fake()->randomElement([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]),
            'status' => fake()->randomElement(['open', 'completed', 'cancelled']),
            'is_active' => fake()->boolean(80), // 80% chance of being active
            'payment_status' => fake()->randomElement(['pending', 'paid', 'failed']),
            'payment_reference' => 'PS_' . fake()->unique()->regexify('[A-Z0-9]{10}'),
        ];
    }

    /**
     * Indicate that the slot is open and active.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'is_active' => true,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * Indicate that the slot is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'is_active' => false,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * Indicate that the slot is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'is_active' => false,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * Indicate that the slot has pending payment.
     */
    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'pending',
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the slot has paid status.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'is_active' => true,
        ]);
    }

    /**
     * Create a slot with specific member count.
     */
    public function withMembers(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'current_members' => $count,
        ]);
    }

    /**
     * Create a slot with specific duration.
     */
    public function withDuration(int $duration): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => $duration,
        ]);
    }

    /**
     * Create a trending slot (recently created with members).
     */
    public function trending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'is_active' => true,
            'payment_status' => 'paid',
            'current_members' => fake()->numberBetween(2, 8),
            'created_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create a full slot (current_members equals max_members).
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'is_active' => true,
            'payment_status' => 'paid',
            'current_members' => 10, // Assuming max_members is 10 for most services
        ]);
    }
} 