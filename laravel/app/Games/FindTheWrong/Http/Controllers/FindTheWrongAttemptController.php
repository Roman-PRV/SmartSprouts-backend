<?php

namespace App\Games\FindTheWrong\Http\Controllers;

use App\Games\FindTheWrong\Http\Requests\SubmitAttemptRequest;
use App\Games\FindTheWrong\Http\Resources\FindTheWrongRevealItemResource;
use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Games\FindTheWrong\Services\FindTheWrongAttemptService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Player-facing endpoint for storing a completed find-the-wrong attempt.
 *
 * GameMatches middleware guarantees {game} resolves to a game with
 * table_prefix = 'find_the_wrong'; $game is type-hinted only to trigger
 * Laravel's implicit route model binding (SubstituteBindings). Without it,
 * {game} stays a raw string and GameMatches throws a LogicException.
 *
 * @OA\Tag(
 *     name="FindTheWrong",
 *     description="Find-the-wrong game — player endpoints"
 * )
 */
class FindTheWrongAttemptController extends Controller
{
    public function __construct(protected FindTheWrongAttemptService $service) {}

    /**
     * Store a completed find-the-wrong attempt and return the reveal data.
     *
     * @OA\Post(
     *     path="/api/games/{game}/levels/{level}/attempts",
     *     tags={"FindTheWrong"},
     *     summary="Submit a completed find-the-wrong attempt",
     *     description="Validates and stores the player's finished attempt. Returns names, explanations and TTS audio for all found and missed items.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="game", in="path", required=true, description="Game ID (must be a find-the-wrong game)", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="level", in="path", required=true, description="Level ID", @OA\Schema(type="integer", example=5)),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/FindTheWrong.SubmitAttemptRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Attempt stored; reveal data returned",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="score", type="integer", example=3),
     *             @OA\Property(property="total_questions", type="integer", example=4),
     *             @OA\Property(property="found_items", type="array", @OA\Items(ref="#/components/schemas/FindTheWrong.RevealItem")),
     *             @OA\Property(property="missed_items", type="array", @OA\Items(ref="#/components/schemas/FindTheWrong.RevealItem"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorResponse", example={"message": "Unauthenticated."})),
     *     @OA\Response(response=404, description="Game or level not found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse", example={"message": "Not found"})),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function store(SubmitAttemptRequest $request, Game $game, FindTheWrongLevel $level): JsonResponse
    {
        $data = $request->validated();

        $found = $data['found'];
        $missedIds = $data['missed_item_ids'];

        /** @var User $user */
        $user = $request->user();

        $result = $this->service->save(
            $user,
            $game,
            $level,
            $found,
            $missedIds,
            $data['duration_seconds'],
            $data['interaction_mode'] ?? 'circle',
        );

        $items = $this->service->loadItems([...array_column($found, 'item_id'), ...$missedIds]);

        return response()->json([
            'score' => $result->score,
            'total_questions' => $result->total_questions,
            'found_items' => $this->resolveFoundItems($found, $items),
            'missed_items' => $this->resolveMissedItems($missedIds, $items),
        ]);
    }

    /**
     * @param  array<int, array{item_id: int, stars: int}>  $found
     * @param  Collection<int, FindTheWrongItem>  $items
     * @return Collection<int, FindTheWrongRevealItemResource>
     */
    private function resolveFoundItems(array $found, Collection $items): Collection
    {
        return collect($found)->map(fn (array $entry) => new FindTheWrongRevealItemResource(
            $items->get($entry['item_id']),
            $entry['stars'],
        ));
    }

    /**
     * @param  array<int, int>  $missedIds
     * @param  Collection<int, FindTheWrongItem>  $items
     * @return Collection<int, FindTheWrongRevealItemResource>
     */
    private function resolveMissedItems(array $missedIds, Collection $items): Collection
    {
        return collect($missedIds)->map(fn (int $id) => new FindTheWrongRevealItemResource(
            $items->get($id)
        ));
    }
}
