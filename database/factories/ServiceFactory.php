<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            'Netflix Premium',
            'Spotify Family',
            'YouTube Premium',
            'Disney+',
            'HBO Max',
            'Amazon Prime',
            'Apple Music',
            'Hulu',
            'Crunchyroll',
            'Paramount+',
            'Peacock',
            'Discovery+',
            'ESPN+',
            'Shudder',
            'Mubi'
        ];

        return [
            'name' => fake()->randomElement($services),
            'description' => fake()->paragraph(),
            'logo' => fake()->imageUrl(100, 100, 'business'),
            'max_members' => fake()->randomElement([5, 6, 8, 10, 12]),
            'price' => fake()->randomFloat(2, 500, 5000),
            'is_active' => fake()->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Indicate that the service is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the service is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a service with specific max members.
     */
    public function withMaxMembers(int $maxMembers): static
    {
        return $this->state(fn (array $attributes) => [
            'max_members' => $maxMembers,
        ]);
    }

    /**
     * Create a service with specific price.
     */
    public function withPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }
} 