<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LevelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function routeFor(Game|int|string $game): string
    {
        $id = $game instanceof Game ? $game->id : (int) $game;

        return "/api/games/{$id}/levels";
    }

    public function test_missing_game_returns_404(): void
    {
        $this->json('GET', $this->routeFor(99999))
            ->assertStatus(404);
    }

    public function test_missing_service_returns_400(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'no_such_prefix',
        ]);

        $response = $this->json('GET', $this->routeFor($game));
        $expectedMessage = "No game service configured for table prefix: {$game->table_prefix}";

        $response->assertStatus(400)
            ->assertJsonStructure(['message'])
            ->assertJsonFragment(['message' => $expectedMessage]);
    }

    public function test_index_returns_levels(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();

        DB::table('true_false_image_levels')->insert([
            [
                'id' => 1,
                'title' => 'First level',
                'image_url' => 'levels/first.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'Second level',
                'image_url' => 'levels/second.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                ['id', 'title', 'image_url'],
            ]);

        $titles = array_column($response->json(), 'title');
        $this->assertEqualsCanonicalizing(['First level', 'Second level'], $titles);
    }

    public function test_show_returns_single_level_with_file_url_and_statements(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('levels/first.png', 'dummy');

        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        config(['filesystems.default' => 'public']);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => 'First level',
            'image_url' => 'levels/first.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => 'The sky is blue',
                'is_true' => true,
                'explanation' => 'Because of Rayleigh scattering',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => 'Cats can fly',
                'is_true' => false,
                'explanation' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson("/api/games/{$game->id}/levels/1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'image_url',
                'statements' => [
                    ['id', 'level_id', 'statement', 'is_true', 'explanation'],
                ],
            ])
            ->assertJson([
                'id' => 1,
                'title' => 'First level',
                'image_url' => 'http://localhost/storage/levels/first.png',
            ]);

        $response->assertJsonFragment([
            'id' => 10,
            'level_id' => 1,
            'statement' => 'The sky is blue',
            'is_true' => true,
            'explanation' => 'Because of Rayleigh scattering',
        ]);

        $response->assertJsonFragment([
            'id' => 11,
            'level_id' => 1,
            'statement' => 'Cats can fly',
            'is_true' => false,
            'explanation' => null,
        ]);

        $this->assertCount(2, $response->json('statements'));
    }

    public function test_check_missing_game_returns_404(): void
    {
        $response = $this->postJson('/api/games/99999/levels/1/check', [
            'answers' => [
                ['statement_id' => 1, 'answer' => true],
            ],
        ]);

        $response->assertStatus(404);
    }

    public function test_check_invalid_payload_returns_422(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        // Missing required fields
        $response = $this->postJson("/api/games/{$game->id}/levels/1/check", []);

        $response->assertStatus(422);

        // Invalid answer type
        $response = $this->postJson("/api/games/{$game->id}/levels/1/check", [
            'answers' => [
                ['statement_id' => 1, 'answer' => 'not a boolean'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_check_statement_from_different_level_returns_400(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            [
                'id' => 1,
                'title' => 'Level 1',
                'image_url' => 'level1.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'Level 2',
                'image_url' => 'level2.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('true_false_image_statements')->insert([
            'id' => 20,
            'level_id' => 2,  // Statement belongs to level 2
            'statement' => 'Statement from level 2',
            'is_true' => true,
            'explanation' => 'Explanation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to check level 1 with statement from level 2
        $response = $this->postJson("/api/games/{$game->id}/levels/1/check", [
            'answers' => [
                ['statement_id' => 20, 'answer' => true],
            ],
        ]);

        $response->assertStatus(422)  // Validation error
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_check_correct_answers_returns_200(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => 'Statement 1',
                'is_true' => true,
                'explanation' => 'Explanation 1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => 'Statement 2',
                'is_true' => false,
                'explanation' => 'Explanation 2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->postJson("/api/games/{$game->id}/levels/1/check", [
            'answers' => [
                ['statement_id' => 10, 'answer' => true],
                ['statement_id' => 11, 'answer' => false],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    ['statement_id', 'correct', 'is_true', 'explanation'],
                ],
            ])
            ->assertJson([
                'results' => [
                    [
                        'statement_id' => 10,
                        'correct' => true,
                        'is_true' => true,
                        'explanation' => 'Explanation 1',
                    ],
                    [
                        'statement_id' => 11,
                        'correct' => true,
                        'is_true' => false,
                        'explanation' => 'Explanation 2',
                    ],
                ],
            ]);
    }

    public function test_check_incorrect_answers_returns_200(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            'id' => 10,
            'level_id' => 1,
            'statement' => 'Statement',
            'is_true' => true,
            'explanation' => 'Explanation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/games/{$game->id}/levels/1/check", [
            'answers' => [
                ['statement_id' => 10, 'answer' => false], // Wrong answer
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'results' => [
                    [
                        'statement_id' => 10,
                        'correct' => false,
                        'is_true' => true,
                    ],
                ],
            ]);
    }

    public function test_check_mixed_answers_returns_proper_results(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => 'Statement 1',
                'is_true' => true,
                'explanation' => 'Explanation 1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => 'Statement 2',
                'is_true' => false,
                'explanation' => 'Explanation 2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->postJson("/api/games/{$game->id}/levels/1/check", [
            'answers' => [
                ['statement_id' => 10, 'answer' => true],  // Correct
                ['statement_id' => 11, 'answer' => true],  // Incorrect
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'results' => [
                    [
                        'statement_id' => 10,
                        'correct' => true,
                    ],
                    [
                        'statement_id' => 11,
                        'correct' => false,
                    ],
                ],
            ]);
    }
}
