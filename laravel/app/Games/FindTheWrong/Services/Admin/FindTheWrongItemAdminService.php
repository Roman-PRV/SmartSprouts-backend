<?php

namespace App\Games\FindTheWrong\Services\Admin;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Helpers\ConfigHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin-side CRUD for FindTheWrongItem rows. Polygon updates fully replace
 * the stored coordinates (no merge). Storage cleanup on delete wipes the
 * item's directory so any TTS audio dropped there by the observer is removed.
 *
 * No ItemAdminServiceInterface by design — items shape diverges per game
 * (polygon here, is_correct in true_false, etc.), so a shared contract would
 * collapse to `array $data` and gain no guarantees. Per-game controllers
 * depend on concrete services. Mirror this layout for new games rather than
 * forcing an interface around it.
 *
 * Tenancy invariant: items belong to find_the_wrong_levels rows, which carry
 * no `game_id` column. The data model assumes exactly one Game record per
 * `table_prefix`, so all find_the_wrong levels (and their items) belong to
 * the single Game with table_prefix='find_the_wrong'. GameMatches middleware
 * enforces that the URL's {game} has the expected prefix, so no per-game
 * scoping is needed at the service level. If we ever introduce multiple
 * Game rows sharing one prefix, the fix lives in the data layer (add
 * game_id FK to levels), not in a where() clause here.
 */
class FindTheWrongItemAdminService
{
    /**
     * Create an item under the given level. Fails fast with ModelNotFoundException
     * if the level does not exist, so the caller surfaces a 404 instead of a
     * silent FK violation.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $levelId, array $data): FindTheWrongItem
    {
        FindTheWrongLevel::query()->findOrFail($levelId);

        /** @var FindTheWrongItem $item */
        $item = FindTheWrongItem::query()->create([
            'level_id' => $levelId,
            'polygon' => $data['polygon'],
            'name' => $data['name'],
            'explanation' => $data['explanation'] ?? [],
        ]);

        return $item;
    }

    /**
     * Polygon, when present, replaces the stored array as a whole. Translatable
     * fields use setTranslation per locale so missing locales are kept
     * (per Spatie semantics).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $itemId, array $data): FindTheWrongItem
    {
        /** @var FindTheWrongItem $item */
        $item = FindTheWrongItem::query()->findOrFail($itemId);

        if (array_key_exists('polygon', $data)) {
            $item->polygon = $data['polygon'];
        }

        if (array_key_exists('name', $data) && is_array($data['name'])) {
            foreach ($data['name'] as $locale => $value) {
                $item->setTranslation('name', $locale, $value);
            }
        }

        if (array_key_exists('explanation', $data) && is_array($data['explanation'])) {
            foreach ($data['explanation'] as $locale => $value) {
                $item->setTranslation('explanation', $locale, $value);
            }
        }

        $item->save();

        return $item;
    }

    /**
     * Delete the item and wipe its storage directory. TTS audio lives under the
     * same directory (via TtsModelMappingEnum mapping aligned with STORAGE_ROOT),
     * so a single recursive deleteDirectory cleans both images and audio.
     * Missing rows surface as ModelNotFoundException for the controller to translate into 404.
     */
    public function delete(int $itemId): void
    {
        /** @var FindTheWrongItem $item */
        $item = FindTheWrongItem::query()->findOrFail($itemId);
        $directory = $item->storageDirectory();
        $item->delete();

        try {
            Storage::disk($this->diskName())->deleteDirectory($directory);
        } catch (Throwable $e) {
            Log::warning('Failed to clean storage on FindTheWrongItem delete', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read the upload disk name from config (env-overridable: dev=public, prod=s3/R2).
     */
    private function diskName(): string
    {
        return ConfigHelper::uploadDisk();
    }
}
