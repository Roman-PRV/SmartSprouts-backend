<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_game_index_returns_401(): void
    {
        $this->getJson('/api/games')
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_access_game_index_returns_200(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/games')
            ->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_index_includes_categories(): void
    {
        Game::factory()->create(['key' => 'find_the_wrong', 'is_active' => true, 'categories' => ['logic']]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/games')
            ->assertStatus(200)
            ->assertJsonStructure([['id', 'key', 'categories']]);
    }

    public function test_index_filters_by_category(): void
    {
        Game::factory()->create(['key' => 'multiplication_table', 'is_active' => true, 'categories' => ['math']]);
        Game::factory()->create(['key' => 'true_false_text', 'is_active' => true, 'categories' => ['reading']]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/games?category=math')
            ->assertStatus(200)
            ->assertJsonCount(1);

        $this->assertSame('multiplication_table', $response->json('0.key'));
    }

    public function test_index_multi_category_game_appears_in_each_of_its_categories(): void
    {
        Game::factory()->create(['key' => 'true_false_image', 'is_active' => true, 'categories' => ['logic', 'reading']]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/games?category=logic')
            ->assertStatus(200)
            ->assertJsonCount(1);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/games?category=reading')
            ->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_index_rejects_invalid_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/games?category=not_a_category')
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_show_missing_game_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/games/99999')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_non_numeric_game_id_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/games/abc')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }
}
