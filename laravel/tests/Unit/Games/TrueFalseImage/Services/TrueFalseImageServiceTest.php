<?php

namespace Tests\Unit\Games\TrueFalseImage\Services;

use App\DTO\CheckAnswersDTO;
use App\Exceptions\TableMissingException;
use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Games\TrueFalseImage\Services\TrueFalseImageService;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrueFalseImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrueFalseImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrueFalseImageService;
    }

    /** @test */
    public function it_returns_correct_result_for_correct_answers(): void
    {
        $level = TrueFalseImageLevel::create([
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
        ]);

        $statement1 = TrueFalseImageStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 1',
            'is_true' => true,
            'explanation' => 'Explanation 1',
        ]);

        $statement2 = TrueFalseImageStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 2',
            'is_true' => false,
            'explanation' => 'Explanation 2',
        ]);

        $answers = [
            ['statement_id' => $statement1->id, 'answer' => true],
            ['statement_id' => $statement2->id, 'answer' => false],
        ];

        $game = Game::factory()->create();
        $user = User::factory()->create();
        $dto = new CheckAnswersDTO($user->id, $game, $level->id, $answers);
        $result = $this->service->check($dto);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);
        $this->assertTrue($result['results'][0]['correct']);
        $this->assertTrue($result['results'][1]['correct']);
    }

    /** @test */
    public function it_returns_incorrect_result_for_wrong_answers(): void
    {
        $level = TrueFalseImageLevel::create([
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
        ]);

        $statement = TrueFalseImageStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement',
            'is_true' => true,
            'explanation' => 'Explanation',
        ]);

        $game = Game::factory()->create();
        $user = User::factory()->create();
        $answers = [['statement_id' => $statement->id, 'answer' => false]];
        $dto = new CheckAnswersDTO($user->id, $game, $level->id, $answers);
        $result = $this->service->check($dto);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertFalse($result['results'][0]['correct']);
        $this->assertTrue($result['results'][0]['is_true']);
    }

    /** @test */
    public function it_throws_exception_for_missing_statement(): void
    {
        $level = TrueFalseImageLevel::create([
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
        ]);

        $payload = [
            'answers' => [
                ['statement_id' => 99999, 'answer' => true],
            ],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('The statement 99999 does not belong to level');

        $game = Game::factory()->create();
        $user = User::factory()->create();

        $dto = new CheckAnswersDTO(
            userId: $user->id,
            game: $game,
            levelId: $level->id,
            answers: $payload['answers']
        );

        $this->service->check($dto);
    }

    /** @test */
    public function it_throws_exception_for_missing_table(): void
    {
        $exceptionThrown = false;

        try {
            // Drop the table to simulate missing table
            \Schema::dropIfExists('true_false_image_statements');

            $payload = [
                'answers' => [
                    ['statement_id' => 1, 'answer' => true],
                ],
            ];

            $game = Game::factory()->create();
            $user = User::factory()->create();

            $dto = new CheckAnswersDTO(
                userId: $user->id,
                game: $game,
                levelId: 1,
                answers: $payload['answers']
            );

            $this->service->check($dto);
        } catch (TableMissingException $e) {
            $exceptionThrown = true;
        } finally {
            // Restore the table for other tests
            \Artisan::call('migrate:fresh', ['--seed' => false]);
        }

        $this->assertTrue($exceptionThrown, 'Expected TableMissingException was not thrown');
    }

    /** @test */
    public function it_returns_mixed_results_for_mixed_answers(): void
    {
        $level = TrueFalseImageLevel::create([
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
        ]);

        $statement1 = TrueFalseImageStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 1',
            'is_true' => true,
            'explanation' => 'Explanation 1',
        ]);

        $statement2 = TrueFalseImageStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 2',
            'is_true' => false,
            'explanation' => 'Explanation 2',
        ]);

        $game = Game::factory()->create();
        $user = User::factory()->create();
        $answers = [
            ['statement_id' => $statement1->id, 'answer' => true],
            ['statement_id' => $statement2->id, 'answer' => true],
        ];
        $dto = new CheckAnswersDTO($user->id, $game, $level->id, $answers);
        $result = $this->service->check($dto);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);
        $this->assertTrue($result['results'][0]['correct']);
        $this->assertFalse($result['results'][1]['correct']);
    }

    /** @test */
    public function it_includes_all_required_fields_in_result(): void
    {
        $level = TrueFalseImageLevel::create([
            'title' => 'Test Level',
            'image_url' => 'test.jpg',
        ]);

        $statement = TrueFalseImageStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement',
            'is_true' => true,
            'explanation' => 'Explanation text',
        ]);

        $game = Game::factory()->create();
        $user = User::factory()->create();
        $answers = [['statement_id' => $statement->id, 'answer' => true]];
        $dto = new CheckAnswersDTO($user->id, $game, $level->id, $answers);
        $result = $this->service->check($dto);

        $this->assertArrayHasKey('results', $result);
        $firstResult = $result['results'][0];

        $this->assertArrayHasKey('statement_id', $firstResult);
        $this->assertArrayHasKey('correct', $firstResult);
        $this->assertArrayHasKey('is_true', $firstResult);
        $this->assertArrayHasKey('explanation', $firstResult);

        $this->assertEquals($statement->id, $firstResult['statement_id']);
        $this->assertTrue($firstResult['correct']);
        $this->assertTrue($firstResult['is_true']);
        $this->assertEquals('Explanation text', $firstResult['explanation']);
    }
}
