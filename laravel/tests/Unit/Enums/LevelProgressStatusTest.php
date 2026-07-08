<?php

namespace Tests\Unit\Enums;

use App\Enums\LevelProgressStatus;
use PHPUnit\Framework\TestCase;

class LevelProgressStatusTest extends TestCase
{
    public function test_perfect_score_is_mastered(): void
    {
        $this->assertSame(LevelProgressStatus::MASTERED, LevelProgressStatus::fromScore(3, 3));
    }

    public function test_partial_score_is_not_perfect(): void
    {
        $this->assertSame(LevelProgressStatus::NOT_PERFECT, LevelProgressStatus::fromScore(1, 3));
    }

    public function test_zero_question_attempt_is_not_perfect(): void
    {
        $this->assertSame(LevelProgressStatus::NOT_PERFECT, LevelProgressStatus::fromScore(0, 0));
    }
}
