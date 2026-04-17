<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Auth guard ───────────────────────────────────────────────────────────

    /** @test */
    public function guest_cannot_access_profile(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    /** @test */
    public function authenticated_user_can_access_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk();
    }

    // ─── Response shape ───────────────────────────────────────────────────────

    /** @test */
    public function response_contains_correct_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

        $this->assertArrayNotHasKey('password', $response->json());
    }

    /** @test */
    public function response_has_expected_json_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonStructure([
                'name',
                'email',
                'stats' => [
                    'totalScore',
                    'totalLevels',
                    'completedLevels',
                    'correctAnswersPercentage',
                ],
            ]);
    }

    // ─── Stats aggregation ────────────────────────────────────────────────────

    /** @test */
    public function stats_are_zero_when_user_has_no_game_results(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson([
                'stats' => [
                    'totalScore' => 0,
                    'totalLevels' => 0,
                    'completedLevels' => 0,
                    'correctAnswersPercentage' => 0.0,
                ],
            ]);
    }

    /** @test */
    public function stats_correctly_aggregate_user_game_results(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->withPrefix('true_false_image')->create(['is_active' => true]);

        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'Level 1']), 'image_url' => 'img1.jpg'],
            ['id' => 2, 'title' => json_encode(['en' => 'Level 2']), 'image_url' => 'img2.jpg'],
        ]);

        DB::table('game_results')->insert([
            [
                'user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1,
                'locale' => 'en', 'score' => 3, 'total_questions' => 5,
                'details' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 2,
                'locale' => 'en', 'score' => 1, 'total_questions' => 2,
                'details' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson([
                'stats' => [
                    'totalScore' => 4,
                    'totalLevels' => 2,
                    'completedLevels' => 2,
                    'correctAnswersPercentage' => round(4 / 7 * 100, 2),
                ],
            ]);
    }

    /** @test */
    public function only_latest_attempt_per_level_is_counted(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->withPrefix('true_false_image')->create(['is_active' => true]);

        DB::table('game_results')->insert([
            [
                'user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1,
                'locale' => 'en', 'score' => 2, 'total_questions' => 5,
                'details' => null,
                'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10),
            ],
            [
                'user_id' => $user->id, 'game_id' => $game->id, 'level_id' => 1,
                'locale' => 'en', 'score' => 5, 'total_questions' => 5,
                'details' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson([
                'stats' => [
                    'completedLevels' => 1,
                    'totalScore' => 5,
                    'correctAnswersPercentage' => 100.0,
                ],
            ]);
    }

    /** @test */
    public function total_levels_counts_active_games_only(): void
    {
        $user = User::factory()->create();

        Game::factory()->withPrefix('true_false_image')->create(['is_active' => true]);
        Game::factory()->withPrefix('true_false_text')->create(['is_active' => false]);

        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'Level 1']), 'image_url' => 'img1.jpg'],
            ['id' => 2, 'title' => json_encode(['en' => 'Level 2']), 'image_url' => 'img2.jpg'],
        ]);

        DB::table('true_false_text_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'Text Level 1']), 'image_url' => 'img3.jpg', 'text' => json_encode(['en' => 'some text'])],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson([
                'stats' => [
                    'totalLevels' => 2,
                ],
            ]);
    }

    /** @test */
    public function it_gracefully_handles_missing_game_tables_without_returning_error(): void
    {
        $user = User::factory()->create();

        // Valid game with tables
        Game::factory()->withPrefix('true_false_image')->create(['is_active' => true]);
        DB::table('true_false_image_levels')->insert([
            ['id' => 1, 'title' => json_encode(['en' => 'Level 1']), 'image_url' => 'img1.jpg'],
        ]);

        // Invalid game without tables
        $invalidPrefix = 'missing_game_prefix';
        Game::factory()->create(['table_prefix' => $invalidPrefix, 'is_active' => true]);

        // Mock the configuration so it thinks this prefix is allowed
        \Illuminate\Support\Facades\Config::set("game_services.map.$invalidPrefix", 'App\Services\MissingGameService');

        // Clear cache just in case since the service uses it
        \Illuminate\Support\Facades\Cache::flush();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJson([
                'stats' => [
                    'totalLevels' => 1,
                ],
            ]);
    }
}
