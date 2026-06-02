<?php

namespace App\Games\FindTheWrong\Http\Requests;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Traits\RespondsWithJsonValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a player's submission for a find-the-wrong level: the partition of
 * items into `found` (with star rating) and `missed_item_ids`, plus elapsed
 * time. Score is intentionally NOT in the payload — the controller derives it
 * server-side as `count(found)` to match the project's score-as-count
 * convention (see App\Services\GameResultService::calculateScore) and prevent
 * tampering of the displayed/stored value.
 *
 * Per-field rules cover uniqueness (`distinct`) and per-row FK scope (`exists`
 * with `level_id` constraint). Cross-array integrity (no overlap, full
 * coverage) requires `withValidator` — those checks span both arrays.
 *
 * @OA\Schema(
 *     schema="FindTheWrong.SubmitAttemptRequest",
 *     type="object",
 *     title="FindTheWrong Submit Attempt Request",
 *     description="Player's finished attempt at a level: which items were hit and missed.",
 *     required={"duration_seconds", "found", "missed_item_ids"},
 *
 *     @OA\Property(property="duration_seconds", type="integer", minimum=0, maximum=3600, example=42),
 *     @OA\Property(
 *         property="found",
 *         type="array",
 *         description="Items the player hit, with star rating computed on the client (1: hit only, 2: IoU >= 0.2, 3: IoU >= 0.5).",
 *
 *         @OA\Items(
 *             type="object",
 *             required={"item_id", "stars"},
 *
 *             @OA\Property(property="item_id", type="integer", example=10),
 *             @OA\Property(property="stars", type="integer", minimum=1, maximum=3, example=3)
 *         )
 *     ),
 *     @OA\Property(
 *         property="missed_item_ids",
 *         type="array",
 *         description="IDs of items the player did not hit. Together with `found`, must cover every item in the level exactly once.",
 *
 *         @OA\Items(type="integer", example=11)
 *     )
 * )
 */
class SubmitAttemptRequest extends FormRequest
{
    use RespondsWithJsonValidation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var FindTheWrongLevel $level */
        $level = $this->route('level');
        $levelId = $level->id;

        $itemExistsInLevel = Rule::exists('find_the_wrong_items', 'id')
            ->where('level_id', $levelId);

        return [
            'duration_seconds' => 'required|integer|min:0|max:3600',

            'found' => 'present|array',
            'found.*' => 'required|array',
            'found.*.item_id' => ['required', 'integer', 'distinct', $itemExistsInLevel],
            'found.*.stars' => 'required|integer|min:1|max:3',

            'missed_item_ids' => 'present|array',
            'missed_item_ids.*' => ['required', 'integer', 'distinct', $itemExistsInLevel],
        ];
    }

    /**
     * Cross-array checks: no item in both partitions, and together they cover
     * every item in the level. Built-in rules can't express either cleanly.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $foundIds = array_map(static fn (array $entry): int => (int) $entry['item_id'], $this->input('found', []));
            $missedIds = array_map(static fn (int|string $id): int => (int) $id, $this->input('missed_item_ids', []));

            if (array_intersect($foundIds, $missedIds) !== []) {
                $v->errors()->add('found', __('An item cannot appear in both found and missed lists.'));

                return;
            }

            /** @var FindTheWrongLevel $level */
            $level = $this->route('level');
            $levelItemCount = FindTheWrongItem::query()->where('level_id', $level->id)->count();

            if (count($foundIds) + count($missedIds) !== $levelItemCount) {
                $v->errors()->add(
                    'found',
                    __('The submitted items must exactly cover all items in the level (found + missed = total).'),
                );
            }
        });
    }
}
