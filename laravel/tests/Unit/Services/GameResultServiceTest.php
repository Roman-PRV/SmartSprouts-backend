<?php

namespace Tests\Unit\Services;

use App\DTO\CheckAnswersDTO;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\User;
use App\Services\GameResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GameResultServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameResultService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GameResultService;
    }

    public function test_save_creates_game_result_with_correct_data(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['table_prefix' => 'test_game']);

        $resultsData = [
            ['statement_id' => 1, 'correct' => true],
            ['statement_id' => 2, 'correct' => false],
        ];

        $dto = new CheckAnswersDTO(
            userId: $user->id,
            game: $game,
            levelId: 5,
            answers: []
        );

        $this->service->save($dto, ['results' => $resultsData]);

        $this->assertDatabaseHas('game_results', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'level_id' => 5,
            'score' => 1,
            'total_questions' => 2,
        ]);

        $savedResult = GameResult::first();
        $this->assertEquals($resultsData, $savedResult->details);
    }

    public function test_save_handles_missing_results_key(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['table_prefix' => 'test_game']);

        $dto = new CheckAnswersDTO($user->id, $game, 5, []);

        $this->service->save($dto, []);

        $this->assertDatabaseHas('game_results', [
            'score' => 0,
            'total_questions' => 0,
        ]);

        $savedResult = GameResult::first();
        $this->assertEquals([], $savedResult->details);
    }

    public function test_save_calculates_score_correctly(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['table_prefix' => 'test_game']);

        $dto = new CheckAnswersDTO($user->id, $game, 5, []);

        $results = [
            'results' => [
                ['correct' => true],
                ['correct' => true],
                ['correct' => false],
                ['correct' => true],
            ],
        ];

        $this->service->save($dto, $results);

        $this->assertDatabaseHas('game_results', [
            'score' => 3,
            'total_questions' => 4,
        ]);
    }

    public function test_save_logs_error_on_database_failure(): void
    {
        $game = Game::factory()->create(['table_prefix' => 'test_game']);
        $invalidUserId = 999999;

        $dto = new CheckAnswersDTO($invalidUserId, $game, 5, []);

        Log::shouldReceive('error')
            ->once()
            ->withAnyArgs();

        $this->service->save($dto, ['results' => []]);

        $this->assertEquals(0, GameResult::count());
    }
}
