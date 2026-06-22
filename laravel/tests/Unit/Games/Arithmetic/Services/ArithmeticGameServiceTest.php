<?php

namespace Tests\Unit\Games\Arithmetic\Services;

use App\DTO\CheckAnswersDTO;
use App\Games\Arithmetic\Models\ArithmeticLevel;
use App\Games\Arithmetic\Services\ArithmeticGameService;
use App\Games\Arithmetic\Support\ArithmeticConstants;
use App\Models\Game;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ArithmeticGameServiceTest extends TestCase
{
    private ArithmeticGameService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Stub operation: distinct symbol and an injective apply() so each
        // expected result is easy to assert (apply(a, b) = a*10 + b).
        $this->service = new class extends ArithmeticGameService
        {
            protected function symbol(): string
            {
                return '?';
            }

            protected function apply(int $operandA, int $operandB): int
            {
                return $operandA * 10 + $operandB;
            }

            protected function levelTitle(int $level): string
            {
                return "Level {$level}";
            }
        };
    }

    /** @test */
    public function facts_for_is_deterministic_and_covers_the_full_range(): void
    {
        $facts = $this->service->factsFor(3);

        $this->assertCount(ArithmeticConstants::FACTS_PER_LEVEL, $facts);

        $first = $facts[0];
        $this->assertSame(1, $first['id']);
        $this->assertSame(1, $first['operand_b']);
        $this->assertSame(3, $first['operand_a']);
        $this->assertSame('?', $first['operator']);
        $this->assertSame(31, $first['result']);

        $last = $facts[ArithmeticConstants::FACTS_PER_LEVEL - 1];
        $this->assertSame(ArithmeticConstants::FACTS_PER_LEVEL, $last['id']);
        $this->assertSame(40, $last['result']);
    }

    /** @test */
    public function fetch_all_levels_returns_bare_levels_without_equations(): void
    {
        $levels = $this->service->fetchAllLevels();

        $this->assertInstanceOf(Collection::class, $levels);
        $this->assertCount(ArithmeticConstants::LEVEL_COUNT, $levels);

        /** @var ArithmeticLevel $first */
        $first = $levels->first();
        $this->assertSame(1, $first->id);
        $this->assertSame('Level 1', $first->title);
        $this->assertSame('?', $first->operator);
        $this->assertEmpty($first->equations);
    }

    /** @test */
    public function fetch_level_returns_level_with_equations(): void
    {
        /** @var ArithmeticLevel $level */
        $level = $this->service->fetchLevel(5);

        $this->assertInstanceOf(ArithmeticLevel::class, $level);
        $this->assertSame(5, $level->id);
        $this->assertSame('?', $level->operator);
        $this->assertCount(ArithmeticConstants::FACTS_PER_LEVEL, $level->equations);
        $this->assertSame(51, $level->equations[0]['result']);
    }

    /** @test */
    public function fetch_level_throws_not_found_below_range(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Level 0 not found');

        $this->service->fetchLevel(0);
    }

    /** @test */
    public function fetch_level_throws_not_found_above_range(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Level 11 not found');

        $this->service->fetchLevel(11);
    }

    /** @test */
    public function levels_fall_back_to_the_default_icon_when_no_directory_is_set(): void
    {
        // The base stub does not override imageDirectory().
        $level = $this->service->fetchLevel(2);

        $this->assertStringContainsString('icons/default-icon.png', $level->image_url);
    }

    /** @test */
    public function levels_resolve_a_unique_icon_from_the_static_disk(): void
    {
        $service = new class extends ArithmeticGameService
        {
            protected function symbol(): string
            {
                return '+';
            }

            protected function apply(int $operandA, int $operandB): int
            {
                return $operandA + $operandB;
            }

            protected function levelTitle(int $level): string
            {
                return "Add {$level}";
            }

            protected function imageDirectory(): ?string
            {
                return 'icons/arithmetic/addition';
            }
        };

        $this->assertStringContainsString(
            'icons/arithmetic/addition/3.png',
            $service->fetchLevel(3)->image_url,
        );
        $this->assertStringContainsString(
            'icons/arithmetic/addition/1.png',
            $service->fetchAllLevels()->first()->image_url,
        );
    }

    /** @test */
    public function the_synthesized_level_refuses_to_be_persisted(): void
    {
        $this->expectException(LogicException::class);

        (new ArithmeticLevel)->save();
    }

    /** @test */
    public function check_throws_not_found_for_an_out_of_range_level(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Level 11 not found');

        $this->service->check(new CheckAnswersDTO(
            userId: 1,
            game: new Game,
            levelId: 11,
            answers: [['equation_id' => 1, 'answer' => 11]],
        ));
    }

    /** @test */
    public function check_throws_for_an_unknown_equation_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->check(new CheckAnswersDTO(
            userId: 1,
            game: new Game,
            levelId: 3,
            answers: [['equation_id' => 99, 'answer' => 5]],
        ));
    }

    /** @test */
    public function check_accepts_a_negative_given_answer(): void
    {
        $result = $this->service->check(new CheckAnswersDTO(
            userId: 1,
            game: new Game,
            levelId: 3,
            answers: [['equation_id' => 1, 'answer' => -5]],
        ));

        $this->assertSame(-5, $result['results'][0]['given_answer']);
        $this->assertFalse($result['results'][0]['correct']);
    }

    /** @test */
    public function check_scores_answers_and_derives_expected_from_apply(): void
    {
        $dto = new CheckAnswersDTO(
            userId: 1,
            game: new Game,
            levelId: 3,
            answers: [
                ['equation_id' => 1, 'answer' => 31],
                ['equation_id' => 2, 'answer' => 99],
            ],
        );

        $result = $this->service->check($dto);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);

        $correct = $result['results'][0];
        $this->assertSame(1, $correct['equation_id']);
        $this->assertSame(31, $correct['expected_answer']);
        $this->assertSame(31, $correct['given_answer']);
        $this->assertTrue($correct['correct']);

        $wrong = $result['results'][1];
        $this->assertSame(2, $wrong['equation_id']);
        $this->assertSame(32, $wrong['expected_answer']);
        $this->assertSame(99, $wrong['given_answer']);
        $this->assertFalse($wrong['correct']);
    }
}
