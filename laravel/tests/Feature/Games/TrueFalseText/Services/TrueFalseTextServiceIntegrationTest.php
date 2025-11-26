<?php

namespace Tests\Feature\Games\TrueFalseText\Services;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use App\Games\TrueFalseText\Services\TrueFalseTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrueFalseTextServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TrueFalseTextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrueFalseTextService;
        // Tables are created via migrations, no need to create manually
    }

    /** @test */
    public function it_can_handle_large_datasets(): void
    {
        // Create multiple levels with statements
        for ($i = 1; $i <= 10; $i++) {
            $level = TrueFalseTextLevel::create([
                'title' => "Level {$i}",
                'text' => "Text for level {$i}",
                'image_url' => "level{$i}.jpg",
            ]);

            // Create 5 statements for each level
            for ($j = 1; $j <= 5; $j++) {
                TrueFalseTextStatement::create([
                    'level_id' => $level->id,
                    'statement' => "Statement {$j} for level {$i}",
                    'is_true' => ($j % 2 === 1), // Alternate true/false
                    'explanation' => "Explanation {$j} for level {$i}",
                ]);
            }
        }

        // Test fetchAllLevels
        $levels = $this->service->fetchAllLevels();
        $this->assertCount(10, $levels);

        // Test fetchLevel with statements
        $levelWithStatements = $this->service->fetchLevel(1);
        $this->assertCount(5, $levelWithStatements->statements);

        // Test fetchDataForLevel
        $statements = $this->service->fetchDataForLevel(1);
        $this->assertCount(5, $statements);
    }

    /** @test */
    public function it_maintains_data_integrity_across_operations(): void
    {
        $level = TrueFalseTextLevel::create([
            'title' => 'Integrity Test Level',
            'text' => 'Testing data integrity',
            'image_url' => 'integrity.jpg',
        ]);

        $statement = TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'This is a test statement',
            'is_true' => true,
            'explanation' => 'This statement is true for testing',
        ]);

        // Fetch all levels and verify our level exists
        $allLevels = $this->service->fetchAllLevels();
        $foundLevel = $allLevels->firstWhere('id', $level->id);
        $this->assertNotNull($foundLevel);
        $this->assertEquals('Integrity Test Level', $foundLevel->title);

        // Fetch specific level with statements
        $levelWithStatements = $this->service->fetchLevel($level->id);
        $this->assertEquals('Integrity Test Level', $levelWithStatements->title);
        $this->assertCount(1, $levelWithStatements->statements);
        $this->assertEquals('This is a test statement', $levelWithStatements->statements->first()->statement);

        // Fetch statements directly
        $statements = $this->service->fetchDataForLevel($level->id);
        $this->assertCount(1, $statements);
        $this->assertTrue($statements->first()->is_true);
        $this->assertEquals('This statement is true for testing', $statements->first()->explanation);
    }

    /** @test */
    public function it_handles_empty_database_gracefully(): void
    {
        // Ensure tables exist but are empty
        $this->assertEquals(0, TrueFalseTextLevel::count());
        $this->assertEquals(0, TrueFalseTextStatement::count());

        $levels = $this->service->fetchAllLevels();
        $this->assertTrue($levels->isEmpty());
    }

    /** @test */
    public function it_handles_levels_without_statements(): void
    {
        $levelWithoutStatements = TrueFalseTextLevel::create([
            'title' => 'Empty Level',
            'text' => 'This level has no statements',
            'image_url' => 'empty.jpg',
        ]);

        $levelWithStatements = TrueFalseTextLevel::create([
            'title' => 'Full Level',
            'text' => 'This level has statements',
            'image_url' => 'full.jpg',
        ]);

        TrueFalseTextStatement::create([
            'level_id' => $levelWithStatements->id,
            'statement' => 'Test statement',
            'is_true' => true,
            'explanation' => 'Test explanation',
        ]);

        // Should get both levels
        $allLevels = $this->service->fetchAllLevels();
        $this->assertCount(2, $allLevels);

        // Should be able to fetch level without statements (but won't load statements relation)
        $emptyLevel = $this->service->fetchLevel($levelWithoutStatements->id);
        $this->assertEquals('Empty Level', $emptyLevel->title);
        $this->assertCount(0, $emptyLevel->statements);

        // Should be able to fetch level with statements
        $fullLevel = $this->service->fetchLevel($levelWithStatements->id);
        $this->assertEquals('Full Level', $fullLevel->title);
        $this->assertCount(1, $fullLevel->statements);

        // fetchDataForLevel should throw exception for level without statements
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No statements found for level {$levelWithoutStatements->id} in true_false_text_statements");
        $this->service->fetchDataForLevel($levelWithoutStatements->id);
    }

    /** @test */
    public function it_preserves_boolean_casting_for_statements(): void
    {
        $level = TrueFalseTextLevel::create([
            'title' => 'Boolean Test Level',
            'text' => 'Testing boolean casting',
            'image_url' => 'boolean.jpg',
        ]);

        // Create statements with different boolean representations
        TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'True statement',
            'is_true' => 1, // Should be cast to true
            'explanation' => 'This should be true',
        ]);

        TrueFalseTextStatement::create([
            'level_id' => $level->id,
            'statement' => 'False statement',
            'is_true' => 0, // Should be cast to false
            'explanation' => 'This should be false',
        ]);

        $statements = $this->service->fetchDataForLevel($level->id);

        $trueStatement = $statements->firstWhere('statement', 'True statement');
        $falseStatement = $statements->firstWhere('statement', 'False statement');

        $this->assertIsBool($trueStatement->is_true);
        $this->assertIsBool($falseStatement->is_true);
        $this->assertTrue($trueStatement->is_true);
        $this->assertFalse($falseStatement->is_true);
    }
}
