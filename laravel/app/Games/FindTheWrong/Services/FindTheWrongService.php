<?php

namespace App\Games\FindTheWrong\Services;

use App\Contracts\GameServiceInterface;
use App\Exceptions\TableMissingException;
use App\Games\FindTheWrong\Http\Resources\FindTheWrongRevealItemResource;
use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Game;
use App\Models\Level;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @OA\Schema(
 *     schema="FindTheWrong.AttemptRequest",
 *     type="object",
 *     title="FindTheWrong Attempt Request",
 *     required={"duration_seconds", "found", "missed_item_ids"},
 *
 *     @OA\Property(property="duration_seconds", type="integer", minimum=0, maximum=3600, example=42),
 *     @OA\Property(
 *         property="found",
 *         type="array",
 *
 *         @OA\Items(
 *             type="object",
 *             required={"item_id", "stars"},
 *
 *             @OA\Property(property="item_id", type="integer", example=10),
 *             @OA\Property(property="stars", type="integer", minimum=1, maximum=3, example=3)
 *         )
 *     ),
 *     @OA\Property(property="missed_item_ids", type="array", @OA\Items(type="integer", example=11)),
 *     @OA\Property(property="interaction_mode", type="string", enum={"circle", "marker"}, nullable=true, example="marker")
 * )
 *
 * @OA\Schema(
 *     schema="FindTheWrong.AttemptResponse",
 *     type="object",
 *     title="FindTheWrong Attempt Response",
 *
 *     @OA\Property(property="score", type="integer", example=3),
 *     @OA\Property(property="total_questions", type="integer", example=4),
 *     @OA\Property(property="found_items", type="array", @OA\Items(ref="#/components/schemas/FindTheWrong.RevealItem")),
 *     @OA\Property(property="missed_items", type="array", @OA\Items(ref="#/components/schemas/FindTheWrong.RevealItem"))
 * )
 */
class FindTheWrongService implements GameServiceInterface
{
    public function __construct(private FindTheWrongAttemptService $attempts) {}

    /**
     * Fetch all levels with the count of their items (used for the list endpoint).
     *
     * @return Collection<int, FindTheWrongLevel>
     *
     * @throws TableMissingException
     */
    public function fetchAllLevels(): Collection
    {
        $levelTable = (new FindTheWrongLevel)->getTable();

        if (! Schema::hasTable($levelTable)) {
            throw new TableMissingException($levelTable);
        }

        $itemsTable = (new FindTheWrongItem)->getTable();

        if (! Schema::hasTable($itemsTable)) {
            throw new TableMissingException($itemsTable);
        }

        /** @var Collection<int, FindTheWrongLevel> $levels */
        $levels = FindTheWrongLevel::query()->withCount('items')->get();

        return $levels;
    }

    /**
     * Fetch a level with eager-loaded items (used for the show endpoint).
     *
     * @throws TableMissingException
     * @throws NotFoundHttpException
     */
    public function fetchLevel(int $levelId): Level
    {
        $levelTable = (new FindTheWrongLevel)->getTable();

        if (! Schema::hasTable($levelTable)) {
            throw new TableMissingException($levelTable);
        }

        $itemsTable = (new FindTheWrongItem)->getTable();

        if (! Schema::hasTable($itemsTable)) {
            throw new TableMissingException($itemsTable);
        }

        $level = FindTheWrongLevel::with('items')->find($levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }

        return $level;
    }

    /**
     * Validate the player's finished attempt, persist it and return the reveal
     * data (names, explanations, audio for found and missed items). Score is
     * derived server-side as the count of found items.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws NotFoundHttpException
     */
    public function submit(User $user, Game $game, int $levelId, array $payload): array
    {
        $level = FindTheWrongLevel::find($levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }

        $validItemIds = FindTheWrongItem::where('level_id', $level->id)->pluck('id')->all();

        $validator = Validator::make($payload, [
            'duration_seconds' => 'required|integer|min:0|max:3600',
            'found' => 'present|array',
            'found.*' => 'required|array',
            'found.*.item_id' => ['required', 'integer', 'distinct', Rule::in($validItemIds)],
            'found.*.stars' => 'required|integer|min:1|max:3',
            'missed_item_ids' => 'present|array',
            'missed_item_ids.*' => ['required', 'integer', 'distinct', Rule::in($validItemIds)],
            'interaction_mode' => 'nullable|in:circle,marker',
        ]);

        $validator->after(function (ValidatorContract $v) use ($payload, $validItemIds): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $foundIds = array_map(static fn (array $entry): int => $entry['item_id'], $payload['found'] ?? []);
            $missedIds = $payload['missed_item_ids'] ?? [];

            if (array_intersect($foundIds, $missedIds) !== []) {
                $v->errors()->add('found', __('validation.find_the_wrong.attempt_overlap'));

                return;
            }

            if (count($foundIds) + count($missedIds) !== count($validItemIds)) {
                $v->errors()->add('found', __('validation.find_the_wrong.attempt_count_mismatch'));
            }
        });

        /** @var array{duration_seconds: int, found: array<int, array{item_id: int, stars: int}>, missed_item_ids: array<int, int>, interaction_mode?: string|null} $data */
        $data = $validator->validate();

        $found = $data['found'];
        $missedIds = $data['missed_item_ids'];

        $result = $this->attempts->save(
            $user,
            $game,
            $level,
            $found,
            $missedIds,
            $data['duration_seconds'],
            $data['interaction_mode'] ?? 'circle',
        );

        $items = $this->attempts->loadItems([...array_column($found, 'item_id'), ...$missedIds]);

        return [
            'score' => $result->score,
            'total_questions' => $result->total_questions,
            'found_items' => array_map(
                static fn (array $entry): FindTheWrongRevealItemResource => new FindTheWrongRevealItemResource(
                    $items->get($entry['item_id']),
                    $entry['stars'],
                ),
                $found,
            ),
            'missed_items' => array_map(
                static fn (int $id): FindTheWrongRevealItemResource => new FindTheWrongRevealItemResource($items->get($id)),
                $missedIds,
            ),
        ];
    }
}
