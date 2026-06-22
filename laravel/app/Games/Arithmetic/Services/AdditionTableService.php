<?php

namespace App\Games\Arithmetic\Services;

/**
 * Addition game on the shared arithmetic engine: level N is the row
 * N+1 … N+FACTS_PER_LEVEL. Only the operation is defined here — all read,
 * validation, scoring and persistence live in ArithmeticGameService.
 */
class AdditionTableService extends ArithmeticGameService
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
        return (string) trans('games.addition_table.level_title', ['n' => $level]);
    }
}
