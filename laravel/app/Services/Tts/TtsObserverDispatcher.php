<?php

namespace App\Services\Tts;

use App\Contracts\TtsAudioInterface;
use App\Helpers\ConfigHelper;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Proactive TTS dispatch logic shared by all model observers.
 *
 * The (source → audio_url) field pairs come from the model itself via
 * `getTtsAudioAttributes()` + `getTtsSourceAttribute()` — relying on the
 * project-wide `_audio_url` suffix convention. Adding a new translatable
 * audio field to a model is enough; observer code does not change.
 *
 * Per-locale diff for `onUpdated` reads `getRawOriginal()` (bypassing
 * Spatie's accessor) and compares per supported locale. `onCreated`
 * short-circuits the diff with an empty original.
 *
 * Why no Cache::lock here (unlike GenerateMissingAudioListener):
 * The listener's lock guards against concurrent READS triggering N duplicate
 * jobs for the same (model, attribute, locale) — a real race when many users
 * hit a fresh item simultaneously. Observers fire on WRITE, which is
 * sequential per row, so there's no race to guard against on the write side.
 *
 * Rapid successive admin saves (e.g. typo → fix) will dispatch multiple jobs
 * for the same key — by design. We accept the extra API call so that the
 * latest save always wins. Adding a lock here would silently drop the
 * correction within the lock window, leaving stale audio for the source text.
 *
 * Known gap: write↔read race.
 * Admin save dispatches Job1 (queued); concurrent client read fires the
 * listener which sees the audio_url still empty and dispatches Job2 — both
 * jobs synthesize the same text. The atomic JSON_SET in setAudioPath keeps
 * the DB consistent, but we pay 2× TTS provider quota.
 *
 * This is tracked in TTS-BE-01 (centralize dedup at GenerateTtsAudioJob
 * handle level) and not fixed here because the proper place for the lock is
 * inside the job itself — that single point of dedup covers all dispatch
 * sources (observer, listener, future ad-hoc callers).
 */
class TtsObserverDispatcher
{
    /**
     * Dispatch `GenerateTtsAudioJob` for every supported locale on every audio
     * attribute the model exposes — used from `created` callbacks.
     */
    public function onCreated(TtsAudioInterface&Model $model): void
    {
        foreach ($model->getTtsAudioAttributes() as $audioAttribute) {
            $sourceAttribute = $model->getTtsSourceAttribute($audioAttribute);
            $this->dispatchChangedLocales($model, $sourceAttribute, $audioAttribute, []);
        }
    }

    /**
     * Per-locale diff against pre-save raw original — dispatch only for
     * locales whose value actually changed. Used from `updated` callbacks.
     */
    public function onUpdated(TtsAudioInterface&Model $model): void
    {
        foreach ($model->getTtsAudioAttributes() as $audioAttribute) {
            $sourceAttribute = $model->getTtsSourceAttribute($audioAttribute);

            if (! $model->wasChanged($sourceAttribute)) {
                continue;
            }

            $this->dispatchChangedLocales(
                $model,
                $sourceAttribute,
                $audioAttribute,
                $this->decodeOriginal($model, $sourceAttribute),
            );
        }
    }

    /**
     * @param  array<string, string>  $original
     */
    private function dispatchChangedLocales(
        TtsAudioInterface&Model $model,
        string $sourceAttribute,
        string $audioAttribute,
        array $original,
    ): void {
        $supported = ConfigHelper::getStringList('app.supported_locales', ['en']);

        foreach ($supported as $locale) {
            $value = $model->getTranslatableAttribute($sourceAttribute, $locale);

            if (empty($value)) {
                continue;
            }

            if (($original[$locale] ?? null) === $value) {
                continue;
            }

            GenerateTtsAudioJob::dispatch(
                TtsAudioContext::make($model, $audioAttribute, $locale)
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function decodeOriginal(Model $model, string $attribute): array
    {
        // getRawOriginal bypasses Spatie's translation accessor and returns
        // the raw JSON string from the DB column as it was before save.
        $raw = $model->getRawOriginal($attribute);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
