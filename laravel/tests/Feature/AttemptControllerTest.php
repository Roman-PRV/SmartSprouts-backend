<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the generic submit endpoint (POST /api/games/{game}/levels/{level}/attempts)
 * dispatched by AttemptController::store. Uses the true_false_image game as the
 * concrete service under test; cross-cutting concerns (404/422/auth) are game-agnostic.
 */
class AttemptControllerTest extends TestCase
{
    use RefreshDatabase;

    private function trueFalseGame(): Game
    {
        return Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);
    }

    private function attemptUrl(Game $game, int|string $level = 1): string
    {
        return "/api/games/{$game->id}/levels/{$level}/attempts";
    }

    public function test_missing_game_returns_404(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/games/99999/levels/1/attempts', [
                'answers' => [
                    ['statement_id' => 1, 'answer' => true],
                ],
            ]);

        $response->assertStatus(404);
    }

    public function test_invalid_payload_returns_422(): void
    {
        $game = $this->trueFalseGame();

        // Missing required fields
        $this->actingAs(User::factory()->create())
            ->postJson($this->attemptUrl($game), [])
            ->assertStatus(422);

        // Invalid answer type
        $this->actingAs(User::factory()->create())
            ->postJson($this->attemptUrl($game), [
                'answers' => [
                    ['statement_id' => 1, 'answer' => 'not a boolean'],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_answer_from_a_different_level_returns_422(): void
    {
        $game = $this->trueFalseGame();

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            [
                'id' => 1,
                'title' => json_encode(['en' => 'Level 1', 'es' => 'Nivel 1', 'uk' => 'Рівень 1']),
                'image_url' => 'level1.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => json_encode(['en' => 'Level 2', 'es' => 'Nivel 2', 'uk' => 'Рівень 2']),
                'image_url' => 'level2.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('true_false_image_statements')->insert([
            'id' => 20,
            'level_id' => 2,  // Statement belongs to level 2
            'statement' => json_encode(['en' => 'Statement from level 2', 'es' => 'Declaración del nivel 2', 'uk' => 'Твердження з рівня 2']),
            'is_true' => true,
            'explanation' => json_encode(['en' => 'Explanation', 'es' => 'Explicación', 'uk' => 'Пояснення']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to submit level 1 with a statement that belongs to level 2
        $response = $this->actingAs(User::factory()->create())
            ->postJson($this->attemptUrl($game), [
                'answers' => [
                    ['statement_id' => 20, 'answer' => true],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_correct_answers_return_scored_results(): void
    {
        $game = $this->trueFalseGame();

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Test Level', 'es' => 'Nivel de prueba', 'uk' => 'Тестовий рівень']),
            'image_url' => 'test.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'Statement 1', 'es' => 'Declaración 1', 'uk' => 'Твердження 1']),
                'is_true' => true,
                'explanation' => json_encode(['en' => 'Explanation 1', 'es' => 'Explicación 1', 'uk' => 'Пояснення 1']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'Statement 2', 'es' => 'Declaración 2', 'uk' => 'Твердження 2']),
                'is_true' => false,
                'explanation' => json_encode(['en' => 'Explanation 2', 'es' => 'Explicación 2', 'uk' => 'Пояснення 2']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson($this->attemptUrl($game), [
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

    public function test_incorrect_answers_return_scored_results(): void
    {
        $game = $this->trueFalseGame();

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Test Level']),
            'image_url' => 'test.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            'id' => 10,
            'level_id' => 1,
            'statement' => json_encode(['en' => 'Statement', 'es' => 'Declaración', 'uk' => 'Твердження']),
            'is_true' => true,
            'explanation' => json_encode(['en' => 'Explanation', 'es' => 'Explicación', 'uk' => 'Пояснення']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson($this->attemptUrl($game), [
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

    public function test_mixed_answers_return_proper_results(): void
    {
        $game = $this->trueFalseGame();

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Test Level']),
            'image_url' => 'test.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'Statement 1']),
                'is_true' => true,
                'explanation' => json_encode(['en' => 'Explanation 1']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'Statement 2']),
                'is_true' => false,
                'explanation' => json_encode(['en' => 'Explanation 2']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson($this->attemptUrl($game), [
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

    public function test_non_numeric_level_id_returns_404(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/levels/abc/attempts", [
                'answers' => [['statement_id' => 1, 'answer' => true]],
            ])
            ->assertStatus(404);
    }

    public function test_non_numeric_game_id_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/games/abc/levels/1/attempts', [
                'answers' => [['statement_id' => 1, 'answer' => true]],
            ])
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_submit(): void
    {
        $game = Game::factory()->create();

        $this->postJson($this->attemptUrl($game), [])->assertStatus(401);
    }
}
