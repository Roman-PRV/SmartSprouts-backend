<?php

namespace App\Observers;

use App\Contracts\TtsAudioInterface;
use App\Services\Tts\TtsObserverDispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Single Eloquent observer for any model implementing TtsAudioInterface.
 *
 * Registered per-class in EventServiceProvider::$observers — one instance
 * runs for every TTS-enabled model. The (source → audio_url) mapping comes
 * from the model itself via `getTtsAudioAttributes()`, so adding a new
 * audio field to a model requires zero observer changes (OCP).
 *
 * Intersection type `TtsAudioInterface&Model` makes misregistration loud:
 * registering on a non-TtsAudioInterface model raises TypeError at first
 * save, instead of silently doing nothing.
 */
class ProactiveTtsObserver
{
    public function __construct(
        private readonly TtsObserverDispatcher $dispatcher,
    ) {}

    public function created(TtsAudioInterface&Model $model): void
    {
        $this->dispatcher->onCreated($model);
    }

    public function updated(TtsAudioInterface&Model $model): void
    {
        $this->dispatcher->onUpdated($model);
    }
}
