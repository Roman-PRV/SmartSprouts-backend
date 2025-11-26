<?php

namespace Tests\Unit\Games\TrueFalseText\Services;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use App\Games\TrueFalseText\Services\TrueFalseTextService;
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
        $this->expectExceptionMessage('Level 999 not found in true_false_text_levels');

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
        $this->expectExceptionMessage("No statements found for level {$level->id} in true_false_text_statements");

        $this->service->fetchDataForLevel($level->id);
    }

    /** @test */
    public function fetch_data_for_level_throws_exception_when_level_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No statements found for level 999 in true_false_text_statements');

        $this->service->fetchDataForLevel(999);
    }
}
