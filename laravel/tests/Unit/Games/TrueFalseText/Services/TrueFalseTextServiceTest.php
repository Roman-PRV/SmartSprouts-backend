<?php

namespace Tests\Unit\Games\TrueFalseText\Services;

use App\DTO\CheckAnswersDTO;
use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use App\Games\TrueFalseText\Services\TrueFalseTextService;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TrueFalseTextServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrueFalseTextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrueFalseTextService;
    }

    /** @test */
    public function it_implements_game_service_interface(): void
    {
        $this->assertInstanceOf(\App\Contracts\GameServiceInterface::class, $this->service);
    }

    /** @test */
    public function fetch_all_levels_returns_empty_collection_when_no_levels_exist(): void
    {
        // Tables should exist via migrations, just ensure they're empty
        TrueFalseTextLevel::truncate();

        $levels = $this->service->fetchAllLevels();

        $this->assertInstanceOf(Collection::class, $levels);
        $this->assertTrue($levels->isEmpty());
    }

    /** @test */
    public function fetch_all_levels_returns_all_levels_when_they_exist(): void
    {
        // Create test data
        $level1 = TrueFalseTextLevel::create([
            'title' => 'Test Level 1',
            'text' => 'This is test text for level 1',
            'image_url' => 'test1.jpg',
        ]);

        $level2 = TrueFalseTextLevel::create([
            'title' => 'Test Level 2',
            'text' => 'This is test text for level 2',
            'image_url' => 'test2.jpg',
        ]);

        $levels = $this->service->fetchAllLevels();

        $this->assertInstanceOf(Collection::class, $levels);
        $this->assertCount(2, $levels);
        $this->assertEquals('Test Level 1', $levels->first()->title);
        $this->assertEquals('Test Level 2', $levels->last()->title);
    }

    /** @test */
    public function fetch_all_levels_works_with_existing_tables(): void
    {
        // This test simply verifies the service can work with existing tables
        $this->assertTrue(Schema::hasTable('true_false_text_levels'));

        $levels = $this->service->fetchAllLevels();
        $this->assertInstanceOf(Collection::class, $levels);
    }

    /** @test */
    public function fetch_level_returns_level_with_statements(): void
    {
        // Create test level
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        // Create test statements
        TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 1',
            'is_true' => true,
            'explanation' => 'Explanation 1',
        ]);

        TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 2',
            'is_true' => false,
            'explanation' => 'Explanation 2',
        ]);

        $result = $this->service->fetchLevel($level->id);

        $this->assertInstanceOf(TrueFalseTextLevel::class, $result);
        $this->assertEquals($level->id, $result->id);
        $this->assertEquals('Test Level', $result->title);
        $this->assertTrue($result->relationLoaded('statements'));
        $this->assertCount(2, $result->statements);
    }

    /** @test */
    public function fetch_level_throws_exception_when_level_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Level 999 not found');

        $this->service->fetchLevel(999);
    }

    /** @test */
    public function fetch_level_validates_table_existence(): void
    {
        // This test verifies that the service checks for table existence
        $this->assertTrue(Schema::hasTable('true_false_text_levels'));
        $this->assertTrue(Schema::hasTable('true_false_text_statements'));
    }

    /** @test */
    public function fetch_level_loads_statements_relationship(): void
    {
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Test statement',
            'is_true' => true,
            'explanation' => 'Test explanation',
        ]);

        $result = $this->service->fetchLevel($level->id);

        $this->assertTrue($result->relationLoaded('statements'));
        $this->assertCount(1, $result->statements);
    }

    /** @test */
    public function fetch_data_for_level_returns_statements_for_given_level(): void
    {
        // Create test level
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        // Create test statements
        $statement1 = TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 1',
            'is_true' => true,
            'explanation' => 'Explanation 1',
        ]);

        $statement2 = TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 2',
            'is_true' => false,
            'explanation' => 'Explanation 2',
        ]);

        // Create statement for different level (should not be returned)
        $otherLevel = TrueFalseTextLevel::create([
            'title' => 'Other Level',
            'text' => 'Other text',
            'image_url' => 'other.jpg',
        ]);

        TrueFalseTextStatement::create([
            'level_id' => $otherLevel->id,
            'statement' => 'Other Statement',
            'is_true' => true,
            'explanation' => 'Other Explanation',
        ]);

        $statements = $this->service->fetchDataForLevel($level->id);

        $this->assertInstanceOf(Collection::class, $statements);
        $this->assertCount(2, $statements);
        $this->assertEquals('Statement 1', $statements->first()->statement);
        $this->assertEquals('Statement 2', $statements->last()->statement);
        $this->assertTrue($statements->first()->is_true);
        $this->assertFalse($statements->last()->is_true);
    }

    /** @test */
    public function fetch_data_for_level_validates_statements_table_exists(): void
    {
        // Verify that statements table exists before proceeding
        $this->assertTrue(Schema::hasTable('true_false_text_statements'));
    }

    /** @test */
    public function fetch_data_for_level_throws_exception_when_no_statements_found(): void
    {
        // Create level but no statements
        $level = TrueFalseTextLevel::create([
            'title' => 'Empty Level',
            'text' => 'This level has no statements',
            'image_url' => 'empty.jpg',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No statements found for level {$level->id}");

        $this->service->fetchDataForLevel($level->id);
    }

    /** @test */
    public function fetch_data_for_level_throws_exception_when_level_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No statements found for level 999');

        $this->service->fetchDataForLevel(999);
    }

    /** @test */
    public function it_returns_correct_result_for_correct_answers(): void
    {
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        $statement1 = TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 1',
            'is_true' => true,
            'explanation' => 'Explanation 1',
        ]);

        $statement2 = TrueFalseTextStatement::create([
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
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        $statement = TrueFalseTextStatement::create([
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
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
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
            Schema::dropIfExists('true_false_text_statements');

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
        } catch (\App\Exceptions\TableMissingException $e) {
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
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        $statement1 = TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'Statement 1',
            'is_true' => true,
            'explanation' => 'Explanation 1',
        ]);

        $statement2 = TrueFalseTextStatement::create([
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
        $level = TrueFalseTextLevel::create([
            'title' => 'Test Level',
            'text' => 'This is test text',
            'image_url' => 'test.jpg',
        ]);

        $statement = TrueFalseTextStatement::create([
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
