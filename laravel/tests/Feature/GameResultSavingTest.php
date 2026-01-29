<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameResultSavingTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_result_is_stored_in_database_after_successful_check(): void
    {
        // 1. Setup game and level
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Test Level']),
            'image_url' => 'test.jpg',
        ]);

        DB::table('true_false_image_statements')->insert([
            'id' => 10,
            'level_id' => 1,
            'statement' => json_encode(['en' => 'Statement 1']),
            'is_true' => true,
        ]);

        $user = User::factory()->create();

        // 2. Perform check request
        $response = $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/levels/1/check", [
                'answers' => [
                    ['statement_id' => 10, 'answer' => true],
                ],
            ]);

        $response->assertStatus(200);

        // 3. Verify database
        $this->assertDatabaseHas('game_results', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'level_id' => 1,
            'score' => 1,
            'total_questions' => 1,
            'locale' => 'en',
        ]);

        $result = DB::table('game_results')->first();
        $details = json_decode($result->details, true);

        $this->assertCount(1, $details);
        $this->assertEquals(10, $details[0]['statement_id']);
        $this->assertTrue($details[0]['correct']);
    }

    public function test_game_result_stores_correct_score_and_total(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'true_false_image']);

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Test Level']),
            'image_url' => 'test.jpg',
        ]);

        DB::table('true_false_image_statements')->insert([
            ['id' => 10, 'level_id' => 1, 'statement' => json_encode(['en' => 'S1']), 'is_true' => true],
            ['id' => 11, 'level_id' => 1, 'statement' => json_encode(['en' => 'S2']), 'is_true' => false],
        ]);

        $user = User::factory()->create();

        // 1 correct, 1 incorrect
        $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/levels/1/check", [
                'answers' => [
                    ['statement_id' => 10, 'answer' => true],  // correct
                    ['statement_id' => 11, 'answer' => true],  // incorrect
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('game_results', [
            'score' => 1,
            'total_questions' => 2,
        ]);
    }

    public function test_game_result_stores_requested_locale(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'true_false_image']);

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => json_encode(['en' => 'Test Level', 'uk' => 'Тест']),
            'image_url' => 'test.jpg',
        ]);

        DB::table('true_false_image_statements')->insert([
            ['id' => 10, 'level_id' => 1, 'statement' => json_encode(['en' => 'S1', 'uk' => 'Т1']), 'is_true' => true],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'uk'])
            ->postJson("/api/games/{$game->id}/levels/1/check", [
                'answers' => [
                    ['statement_id' => 10, 'answer' => true],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('game_results', [
            'locale' => 'uk',
        ]);
    }
}
