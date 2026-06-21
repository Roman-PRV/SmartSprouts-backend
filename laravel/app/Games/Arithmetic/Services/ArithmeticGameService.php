<?php

namespace App\Games\Arithmetic\Services;

use App\Contracts\GameServiceInterface;
use App\DTO\CheckAnswersDTO;
use App\Games\Arithmetic\Models\ArithmeticLevel;
use App\Games\Arithmetic\Support\ArithmeticConstants;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared engine for the arithmetic pair-match game family (multiplication,
 * addition, …). Every game has an identical structure — ArithmeticConstants::LEVEL_COUNT
 * levels, level N being ArithmeticConstants::FACTS_PER_LEVEL facts of the form
 * `N ∘ b` — so all read and check logic lives here. A concrete game supplies
 * only the operation via symbol(), apply() and levelTitle(); content is
 * deterministic, so there are no tables, item models or admin screens.
 */
abstract class ArithmeticGameService implements GameServiceInterface
{
    /**
     * Operation symbol shown on equation cards (e.g. "×", "+").
     */
    abstract protected function symbol(): string;

    /**
     * Apply the operation to two operands (e.g. multiplication, addition).
     */
    abstract protected function apply(int $operandA, int $operandB): int;

    /**
     * Localized title for a level (e.g. "Multiply by 3"). The subclass resolves
     * it through trans()/__().
     */
    abstract protected function levelTitle(int $level): string;

    /**
     * Static-disk directory holding this game's per-level icons, e.g.
     * "icons/arithmetic/multiplication". Files are expected as "{level}.png".
     * Returns null to use the default icon for every level. Override
     * levelImage() instead for a non-conventional mapping.
     */
    protected function imageDirectory(): ?string
    {
        return null;
    }

    /**
     * Static-disk-relative path to a level's icon, or null to fall back to the
     * default icon. Follows the "{imageDirectory}/{level}.png" convention by
     * default; override for a custom per-level mapping.
     */
    protected function levelImage(int $level): ?string
    {
        $directory = $this->imageDirectory();

        return $directory === null ? null : "{$directory}/{$level}.png";
    }

    /**
     * Build the deterministic facts for a level: operand_a is the level number,
     * operand_b runs 1..FACTS_PER_LEVEL (also used as the fact id).
     *
     * @return array<int, array{id: int, operand_a: int, operand_b: int, operator: string, result: int}>
     */
    public function factsFor(int $level): array
    {
        $facts = [];

        for ($operandB = 1; $operandB <= ArithmeticConstants::FACTS_PER_LEVEL; $operandB++) {
            $facts[] = [
                // Invariant: the fact id IS operand_b (1..FACTS_PER_LEVEL).
                // ArithmeticAttemptRequest's coverage check relies on this — it
                // validates equation_id against the same 1..FACTS_PER_LEVEL range.
                // Keep id == operand_b if the fact generation ever changes.
                'id' => $operandB,
                'operand_a' => $level,
                'operand_b' => $operandB,
                'operator' => $this->symbol(),
                'result' => $this->apply($level, $operandB),
            ];
        }

        return $facts;
    }

    /**
     * All levels for the game, without equations (list shape). Content is
     * synthesized in memory — there is no table to probe.
     *
     * @return Collection<int, ArithmeticLevel>
     */
    public function fetchAllLevels(): Collection
    {
        $levels = new Collection;

        for ($id = 1; $id <= ArithmeticConstants::LEVEL_COUNT; $id++) {
            $levels->push($this->makeLevel($id));
        }

        return $levels;
    }

    /**
     * A single level with its equations (show shape).
     *
     * @throws NotFoundHttpException
     */
    public function fetchLevel(int $levelId): Level
    {
        $this->assertLevelExists($levelId);

        $level = $this->makeLevel($levelId);
        $level->equations = $this->factsFor($levelId);

        return $level;
    }

    /**
     * Equations for a level as a collection (data-only access). The interface
     * returns an Eloquent collection for model-backed games; arithmetic content
     * is synthesized arrays, so the element type intentionally diverges from the
     * Model-bound generic.
     */
    public function fetchDataForLevel(int $levelId): Collection
    {
        /** @phpstan-ignore-next-line arithmetic facts are synthesized arrays, not Eloquent models */
        return new Collection($this->factsFor($levelId));
    }

    /**
     * Score a player's answers for a level. Each answer is compared against the
     * canonical result derived from factsFor() (never from the payload), so the
     * stored score cannot be tampered with. The `correct` key is required by
     * GameResultService::calculateScore.
     *
     * @return array{results: array<int, array{equation_id: int, operand_a: int, operand_b: int, operator: string, given_answer: int, expected_answer: int, correct: bool}>}
     *
     * @throws NotFoundHttpException
     */
    public function check(CheckAnswersDTO $dto): array
    {
        $this->assertLevelExists($dto->levelId);

        $factsById = array_column($this->factsFor($dto->levelId), null, 'id');

        $results = [];

        foreach ($dto->answers as $answer) {
            $equationId = (int) $answer['equation_id'];

            if (! isset($factsById[$equationId])) {
                continue;
            }

            $fact = $factsById[$equationId];
            $givenAnswer = (int) $answer['answer'];

            $results[] = [
                'equation_id' => $equationId,
                'operand_a' => $fact['operand_a'],
                'operand_b' => $fact['operand_b'],
                'operator' => $fact['operator'],
                'given_answer' => $givenAnswer,
                'expected_answer' => $fact['result'],
                'correct' => $givenAnswer === $fact['result'],
            ];
        }

        return ['results' => $results];
    }

    /**
     * Guard that a level id is within the deterministic 1..LEVEL_COUNT range.
     *
     * @throws NotFoundHttpException
     */
    private function assertLevelExists(int $levelId): void
    {
        if ($levelId < 1 || $levelId > ArithmeticConstants::LEVEL_COUNT) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }
    }

    /**
     * Build a bare level (id, title, operator) without equations.
     */
    private function makeLevel(int $id): ArithmeticLevel
    {
        $level = new ArithmeticLevel;
        $level->id = $id;
        $level->title = $this->levelTitle($id);
        $level->operator = $this->symbol();
        $level->image_url = $this->levelImage($id);

        return $level;
    }
}
