<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\Review;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest can retrieve only visible reviews.
     */
    public function test_guest_can_only_retrieve_visible_reviews(): void
    {
        Review::create([
            'name' => 'Visible Guy 1',
            'message' => 'Love the platform!',
            'is_visible' => true,
        ]);

        Review::create([
            'name' => 'Visible Guy 2',
            'message' => 'Super fast service.',
            'is_visible' => true,
        ]);

        Review::create([
            'name' => 'Invisible Guy',
            'message' => 'Should not be seen.',
            'is_visible' => false,
        ]);

        $response = $this->getJson('/api/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Visible Guy 1'])
            ->assertJsonFragment(['name' => 'Visible Guy 2'])
            ->assertJsonMissing(['name' => 'Invisible Guy']);
    }

    /**
     * Test guest can submit a review.
     */
    public function test_guest_can_submit_review(): void
    {
        $payload = [
            'name' => 'Reviewer Name',
            'message' => 'This is a test review.',
        ];

        $response = $this->postJson('/api/reviews', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'message',
                    'is_visible',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('reviews', [
            'name' => 'Reviewer Name',
            'message' => 'This is a test review.',
            'is_visible' => false,
        ]);
    }

    /**
     * Test guest can submit a review without a name (nullable).
     */
    public function test_guest_can_submit_review_without_name(): void
    {
        $payload = [
            'message' => 'This is an anonymous review.',
        ];

        $response = $this->postJson('/api/reviews', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'name' => null,
            'message' => 'This is an anonymous review.',
            'is_visible' => false,
        ]);
    }

    /**
     * Test submission fails if message is missing.
     */
    public function test_submission_fails_if_message_is_missing(): void
    {
        $payload = [
            'name' => 'Name Only',
        ];

        $response = $this->postJson('/api/reviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }
}
