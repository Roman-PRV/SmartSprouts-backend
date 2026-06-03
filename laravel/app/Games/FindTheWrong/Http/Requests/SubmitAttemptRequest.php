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
 * Per-row FK scope is enforced via `Rule::in` over level item IDs that are
 * pre-fetched once per request and cached in `$validItemIds`. This avoids
 * the N+1 that `Rule::exists` causes on `*` array fields. `withValidator`
 * reuses the same cache for the full-coverage check (no extra DB query).
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

    /** @var array<int, int>|null */
    private ?array $validItemIds = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validItemIds = $this->getValidItemIds();

        return [
            'duration_seconds' => 'required|integer|min:0|max:3600',

            'found' => 'present|array',
            'found.*' => 'required|array',
            'found.*.item_id' => ['required', 'integer', 'distinct', Rule::in($validItemIds)],
            'found.*.stars' => 'required|integer|min:1|max:3',

            'missed_item_ids' => 'present|array',
            'missed_item_ids.*' => ['required', 'integer', 'distinct', Rule::in($validItemIds)],
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

            $foundIds = array_map(static fn (array $entry): int => $entry['item_id'], $this->input('found', []));
            $missedIds = $this->input('missed_item_ids', []);

            if (array_intersect($foundIds, $missedIds) !== []) {
                $v->errors()->add('found', __('validation.find_the_wrong.attempt_overlap'));

                return;
            }

            if (count($foundIds) + count($missedIds) !== count($this->getValidItemIds())) {
                $v->errors()->add('found', __('validation.find_the_wrong.attempt_count_mismatch'));
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function getValidItemIds(): array
    {
        if ($this->validItemIds === null) {
            /** @var FindTheWrongLevel $level */
            $level = $this->route('level');
            $this->validItemIds = FindTheWrongItem::where('level_id', $level->id)->pluck('id')->all();
        }

        return $this->validItemIds;
    }
}
