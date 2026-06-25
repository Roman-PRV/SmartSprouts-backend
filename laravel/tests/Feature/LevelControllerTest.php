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

    public function test_index_includes_progress_from_last_attempt(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'true_false_image']);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'L1']), 'image_url' => '', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'title' => json_encode(['en' => 'L2']), 'image_url' => '', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'title' => json_encode(['en' => 'L3']), 'image_url' => '', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        DB::table('game_results')->insert([
            // level 1: last attempt perfect -> mastered
            ['user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1, 'locale' => 'en', 'score' => 3, 'total_questions' => 3, 'details' => null, 'created_at' => now(), 'updated_at' => now()],
            // level 2: completed but not perfect -> not_perfect
            ['user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 2, 'locale' => 'en', 'score' => 1, 'total_questions' => 3, 'details' => null, 'created_at' => now(), 'updated_at' => now()],
            // level 3: only another user's perfect attempt -> must stay not_started for $user
            ['user_id' => $otherUser->id, 'game_id' => $game->id, 'level_id' => 3, 'locale' => 'en', 'score' => 3, 'total_questions' => 3, 'details' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200);

        $byId = collect($response->json())->keyBy('id');
        $this->assertSame('mastered', $byId[1]['progress']);
        $this->assertSame('not_perfect', $byId[2]['progress']);
        $this->assertSame('not_started', $byId[3]['progress']);
    }

    public function test_index_progress_uses_last_attempt_not_best(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'true_false_image']);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'L1']), 'image_url' => '', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $user = User::factory()->create();

        // Older perfect attempt followed by a newer imperfect one: the last wins.
        DB::table('game_results')->insert([
            ['user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1, 'locale' => 'en', 'score' => 3, 'total_questions' => 3, 'details' => null, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
            ['user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1, 'locale' => 'en', 'score' => 2, 'total_questions' => 3, 'details' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200);
        $this->assertSame('not_perfect', $response->json('0.progress'));
    }

    public function test_index_zero_question_attempt_is_not_mastered(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'true_false_image']);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'L1']), 'image_url' => '', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $user = User::factory()->create();

        // A degenerate 0-question attempt must not read as a perfect run (0 === 0).
        DB::table('game_results')->insert([
            ['user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1, 'locale' => 'en', 'score' => 0, 'total_questions' => 0, 'details' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200);
        $this->assertSame('not_perfect', $response->json('0.progress'));
    }

    public function test_index_progress_does_not_leak_across_games(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'true_false_image']);
        $otherGame = Game::factory()->create(['table_prefix' => 'true_false_text']);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'L1']), 'image_url' => '', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $user = User::factory()->create();

        // Perfect attempt for the SAME level_id but a DIFFERENT game. level_id is
        // unique only within a game, so the game_id filter must keep this out.
        DB::table('game_results')->insert([
            ['user_id' => $user->id, 'game_id' => $otherGame->id, 'level_id' => 1, 'locale' => 'en', 'score' => 3, 'total_questions' => 3, 'details' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200);
        $this->assertSame('not_started', $response->json('0.progress'));
    }

    public function test_show_returns_single_level_with_file_url_and_statements(): void
    {
        Storage::fake('public', ['url' => config('app.url').'/storage']);

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
                'statement_audio_url' => json_encode(['en' => 'stmt10_en.mp3']),
                'explanation_audio_url' => json_encode(['en' => 'expl10_en.mp3']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => json_encode(['en' => 'Cats can fly', 'es' => 'Los gatos pueden volar', 'uk' => 'Коти можуть літати']),
                'is_true' => false,
                'explanation' => null,
                'statement_audio_url' => null,
                'explanation_audio_url' => null,
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
                    ['id', 'level_id', 'statement', 'explanation', 'statement_audio_url', 'explanation_audio_url'],
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
            'statement_audio_url' => 'http://localhost/storage/stmt10_en.mp3',
            'explanation_audio_url' => 'http://localhost/storage/expl10_en.mp3',
        ]);

        $response->assertJsonFragment([
            'id' => 11,
            'level_id' => 1,
            'statement' => 'Cats can fly',
            'explanation' => null,
            'statement_audio_url' => null,
            'explanation_audio_url' => null,
        ]);

        $this->assertCount(2, $response->json('statements'));
    }

    public function test_image_url_is_built_without_existence_probe(): void
    {
        // Upload disk faked but the file is deliberately NOT written: the
        // accessor must still return the constructed URL (404 at fetch time),
        // never a silent default-icon swap. The frontend handles the 404.
        Storage::fake('public', ['url' => config('app.url').'/storage']);

        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Lvl', 'es' => 'Nivel', 'uk' => 'Рівень']),
            'image_url' => 'levels/missing.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$game->id}/levels/1")
            ->assertStatus(200)
            ->assertJson(['image_url' => 'http://localhost/storage/levels/missing.png']);
    }

    public function test_empty_image_url_falls_back_to_default_icon(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Lvl', 'es' => 'Nivel', 'uk' => 'Рівень']),
            'image_url' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$game->id}/levels/1");

        $response->assertStatus(200);
        $this->assertStringContainsString('icons/default-icon.png', $response->json('image_url'));
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
        Storage::fake('public', ['url' => config('app.url').'/storage']);

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
            'statement_audio_url' => json_encode([
                'uk' => 'stmt_uk.mp3',
                'en' => 'stmt_en.mp3',
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

        if ($locale === 'uk') {
            $this->assertEquals('http://localhost/storage/stmt_uk.mp3', $statements[0]['statement_audio_url']);
        } elseif ($locale === 'es') {
            $this->assertNull($statements[0]['statement_audio_url']);
        } else {
            $this->assertEquals('http://localhost/storage/stmt_en.mp3', $statements[0]['statement_audio_url']);
        }
    }

    public function test_non_numeric_level_id_on_show_returns_404(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->actingAs($user)->getJson("/api/games/{$game->id}/levels/abc")->assertStatus(404);
    }

    public function test_non_numeric_game_id_returns_404_for_level_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/games/abc/levels')->assertStatus(404);
        $this->actingAs($user)->getJson('/api/games/abc/levels/1')->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_game_routes(): void
    {
        $game = Game::factory()->create();

        $this->getJson('/api/games')->assertStatus(401);
        $this->getJson("/api/games/{$game->id}/levels")->assertStatus(401);
        $this->getJson("/api/games/{$game->id}/levels/1")->assertStatus(401);
    }
}
