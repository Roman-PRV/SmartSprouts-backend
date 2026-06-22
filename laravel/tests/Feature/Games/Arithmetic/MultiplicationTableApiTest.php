<?php

namespace Tests\Feature\Games\Arithmetic;

use App\Games\Arithmetic\Support\ArithmeticConstants;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the multiplication_table game over the shared generic
 * endpoints (read via LevelController, submit via AttemptController). The game
 * has no tables — content is synthesized by the arithmetic engine — so the only
 * setup is registering a Game row with the matching table_prefix.
 */
class MultiplicationTableApiTest extends TestCase
{
    use RefreshDatabase;

    private function multiplicationGame(): Game
    {
        return Game::factory()->create([
            'table_prefix' => 'multiplication_table',
            'key' => 'multiplication_table',
        ]);
    }

    /**
     * All ten correct answers for a level: equation_id b → answer level×b.
     *
     * @return array<int, array{equation_id: int, answer: int}>
     */
    private function correctAnswersFor(int $level): array
    {
        $answers = [];

        for ($b = 1; $b <= ArithmeticConstants::FACTS_PER_LEVEL; $b++) {
            $answers[] = ['equation_id' => $b, 'answer' => $level * $b];
        }

        return $answers;
    }

    public function test_lists_all_levels_in_the_requested_locale(): void
    {
        $game = $this->multiplicationGame();

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200)
            ->assertJsonCount(ArithmeticConstants::LEVEL_COUNT)
            ->assertJsonStructure([['id', 'title', 'image_url']])
            ->assertJsonFragment(['title' => 'Multiply by 3']);

        // List shape comes from LevelDescriptionResource — no per-level operator.
        $this->assertArrayNotHasKey('operator', $response->json()[0]);
        $this->assertStringContainsString('icons/arithmetic/multiplication/1.png', $response->json()[0]['image_url']);
    }

    public function test_shows_a_single_level_with_operator_and_equations(): void
    {
        $game = $this->multiplicationGame();

        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$game->id}/levels/3");

        $response->assertStatus(200)
            ->assertJsonPath('operator', '×')
            ->assertJsonCount(ArithmeticConstants::FACTS_PER_LEVEL, 'equations')
            ->assertJsonPath('equations.0', ['id' => 1, 'operand_a' => 3, 'operand_b' => 1, 'result' => 3])
            ->assertJsonPath('equations.9', ['id' => 10, 'operand_a' => 3, 'operand_b' => 10, 'result' => 30]);
    }

    public function test_show_out_of_range_level_returns_404(): void
    {
        $game = $this->multiplicationGame();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$game->id}/levels/11")
            ->assertStatus(404);
    }

    public function test_all_correct_answers_score_full_and_persist_the_result(): void
    {
        $game = $this->multiplicationGame();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/games/{$game->id}/levels/3/attempts", [
                'answers' => $this->correctAnswersFor(3),
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(ArithmeticConstants::FACTS_PER_LEVEL, 'results');

        foreach ($response->json('results') as $result) {
            $this->assertTrue($result['correct']);
        }

        $this->assertDatabaseHas('game_results', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'level_id' => 3,
            'score' => ArithmeticConstants::FACTS_PER_LEVEL,
            'total_questions' => ArithmeticConstants::FACTS_PER_LEVEL,
            'locale' => 'en',
        ]);
    }

    public function test_partial_coverage_returns_422(): void
    {
        $game = $this->multiplicationGame();

        // Drop the last fact — the coverage check must reject an incomplete set.
        $answers = array_slice($this->correctAnswersFor(3), 0, ArithmeticConstants::FACTS_PER_LEVEL - 1);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/3/attempts", ['answers' => $answers])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_out_of_range_equation_id_returns_422(): void
    {
        $game = $this->multiplicationGame();

        $answers = $this->correctAnswersFor(3);
        $answers[0]['equation_id'] = 11; // outside 1..FACTS_PER_LEVEL

        $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/3/attempts", ['answers' => $answers])
            ->assertStatus(422);
    }

    public function test_a_negative_answer_is_accepted_and_scored_wrong(): void
    {
        $game = $this->multiplicationGame();

        $answers = $this->correctAnswersFor(3);
        $answers[0]['answer'] = -5; // valid integer, wrong value

        $response = $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/3/attempts", ['answers' => $answers]);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.given_answer', -5)
            ->assertJsonPath('results.0.correct', false);
    }

    public function test_submit_to_a_nonexistent_level_returns_404(): void
    {
        $game = $this->multiplicationGame();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/games/{$game->id}/levels/999/attempts", [
                'answers' => $this->correctAnswersFor(999),
            ])
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_the_game(): void
    {
        $game = $this->multiplicationGame();

        $this->getJson("/api/games/{$game->id}/levels")->assertStatus(401);
        $this->getJson("/api/games/{$game->id}/levels/3")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/levels/3/attempts", [])->assertStatus(401);
    }
}
