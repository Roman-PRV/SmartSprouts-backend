<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
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
        $this->actingAs(User::factory()->create())
            ->json('GET', $this->routeFor(99999))
            ->assertStatus(404);
    }

    public function test_missing_service_returns_400(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'no_such_prefix',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->json('GET', $this->routeFor($game));
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
                'title' => json_encode(['en' => 'First level', 'es' => 'Primer nivel', 'uk' => 'Перший рівень']),
                'image_url' => 'levels/first.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => json_encode(['en' => 'Second level', 'es' => 'Segundo nivel', 'uk' => 'Другий рівень']),
                'image_url' => 'levels/second.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$game->id}/levels");

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
            'title' => json_encode(['en' => 'First level', 'es' => 'Primer nivel', 'uk' => 'Перший рівень']),
            'image_url' => 'levels/first.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'The sky is blue', 'es' => 'El cielo es azul', 'uk' => 'Небо блакитне']),
                'is_true' => true,
                'explanation' => json_encode(['en' => 'Because of Rayleigh scattering', 'es' => 'Debido a la dispersión de Rayleigh', 'uk' => 'Через розсіювання Релея']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'Cats can fly', 'es' => 'Los gatos pueden volar', 'uk' => 'Коти можуть літати']),
                'is_true' => false,
                'explanation' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$game->id}/levels/1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'image_url',
                'statements' => [
                    ['id', 'level_id', 'statement', 'explanation'],
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
            'explanation' => 'Because of Rayleigh scattering',
        ]);

        $response->assertJsonFragment([
            'id' => 11,
            'level_id' => 1,
            'statement' => 'Cats can fly',
            'explanation' => null,
        ]);

        $this->assertCount(2, $response->json('statements'));
    }

    public function test_check_missing_game_returns_404(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/games/99999/levels/1/check', [
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
        $response = $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/1/check", []);

        $response->assertStatus(422);

        // Invalid answer type
        $response = $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/1/check", [
                'answers' => [
                    ['statement_id' => 1, 'answer' => 'not a boolean'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_check_statement_from_different_level_returns_422(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

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

        // Try to check level 1 with statement from level 2
        $response = $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/1/check", [
                'answers' => [
                    ['statement_id' => 20, 'answer' => true],
                ],
            ]);

        $response->assertStatus(422)
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
            ->postJson("/api/games/{$game->id}/levels/1/check", [
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
            ->postJson("/api/games/{$game->id}/levels/1/check", [
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
            ->postJson("/api/games/{$game->id}/levels/1/check", [
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

    /**
     * @dataProvider localeProvider
     */
    public function test_returns_levels_in_requested_locale(string $locale, string $expectedTitle): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();

        // Create level with translations for all supported locales
        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode([
                'en' => 'First level',
                'es' => 'Primer nivel',
                'uk' => 'Перший рівень',
            ]),
            'image_url' => 'levels/first.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept-Language' => $locale])
            ->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'title' => $expectedTitle,
            ]);
    }

    public static function localeProvider(): array
    {
        return [
            'English locale' => ['en', 'First level'],
            'Spanish locale' => ['es', 'Primer nivel'],
            'Ukrainian locale' => ['uk', 'Перший рівень'],
        ];
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_show_returns_level_in_requested_locale(string $locale, string $expectedTitle): void
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
            'title' => json_encode([
                'en' => 'First level',
                'es' => 'Primer nivel',
                'uk' => 'Перший рівень',
            ]),
            'image_url' => 'levels/first.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            'id' => 10,
            'level_id' => 1,
            'statement' => json_encode([
                'en' => 'The sky is blue',
                'es' => 'El cielo es azul',
                'uk' => 'Небо блакитне',
            ]),
            'is_true' => true,
            'explanation' => json_encode([
                'en' => 'Because of Rayleigh scattering',
                'es' => 'Debido a la dispersión de Rayleigh',
                'uk' => 'Через розсіювання Релея',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept-Language' => $locale])
            ->getJson("/api/games/{$game->id}/levels/1");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => $expectedTitle,
            ]);

        // Verify statements are also localized
        $statements = $response->json('statements');
        $this->assertNotEmpty($statements);
    }

    public function test_unauthenticated_user_cannot_access_game_routes(): void
    {
        $game = Game::factory()->create();

        $this->getJson('/api/games')->assertStatus(401);
        $this->getJson("/api/games/{$game->id}/levels")->assertStatus(401);
        $this->getJson("/api/games/{$game->id}/levels/1")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/levels/1/check", [])->assertStatus(401);
    }
}
