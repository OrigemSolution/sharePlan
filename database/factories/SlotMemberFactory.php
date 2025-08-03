<?php

namespace Database\Factories;

use App\Models\SlotMember;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlotMember>
 */
class SlotMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slot_id' => Slot::factory(),
            'user_id' => User::factory(),
            'member_name' => fake()->name(),
            'member_email' => fake()->unique()->safeEmail(),
            'member_phone' => fake()->phoneNumber(),
            'payment_status' => fake()->randomElement(['pending', 'paid', 'failed']),
            'payment_id' => null, // Will be set when payment is created
        ];
    }

    /**
     * Indicate that the member has paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
        ]);
    }

    /**
     * Indicate that the member has pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'pending',
        ]);
    }

    /**
     * Indicate that the member has failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'failed',
        ]);
    }

    /**
     * Create a guest member (no user_id).
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'member_name' => fake()->name(),
            'member_email' => fake()->unique()->safeEmail(),
            'member_phone' => fake()->phoneNumber(),
        ]);
    }

    /**
     * Create a member for a specific slot.
     */
    public function forSlot(Slot $slot): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_id' => $slot->id,
        ]);
    }

    /**
     * Create a member with specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'member_name' => $user->name,
            'member_email' => $user->email,
            'member_phone' => $user->phone ?? fake()->phoneNumber(),
        ]);
    }
} 