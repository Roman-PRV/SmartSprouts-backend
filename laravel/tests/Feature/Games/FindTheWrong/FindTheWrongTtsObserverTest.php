<?php

namespace Tests\Feature\Games\FindTheWrong;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Jobs\Tts\GenerateTtsAudioJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FindTheWrongTtsObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Create a level via factory without firing observers — dependency-only
     * setup, keeps `Queue::assertPushed` counts focused on the unit under test.
     */
    private function makeLevelSilently(): FindTheWrongLevel
    {
        /** @var FindTheWrongLevel $level */
        $level = FindTheWrongLevel::withoutEvents(
            fn () => FindTheWrongLevel::factory()->create()
        );

        return $level;
    }

    public function test_creating_item_with_all_locales_dispatches_six_jobs(): void
    {
        $level = $this->makeLevelSilently();

        $item = FindTheWrongItem::factory()->create([
            'level_id' => $level->id,
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'explanation' => ['en' => 'Belongs in laundry', 'uk' => 'Не на кухні', 'es' => 'Pertenece a lavandería'],
        ]);

        Queue::assertPushed(GenerateTtsAudioJob::class, 6);

        foreach (['en', 'uk', 'es'] as $locale) {
            foreach (['name_audio_url', 'explanation_audio_url'] as $attribute) {
                Queue::assertPushed(
                    GenerateTtsAudioJob::class,
                    fn (GenerateTtsAudioJob $job) => $job->model->is($item)
                        && $job->attribute === $attribute
                        && $job->locale === $locale,
                );
            }
        }
    }

    public function test_creating_item_skips_empty_explanation_locales(): void
    {
        $level = $this->makeLevelSilently();

        FindTheWrongItem::factory()->create([
            'level_id' => $level->id,
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'explanation' => ['en' => 'Belongs in laundry', 'uk' => '', 'es' => ''],
        ]);

        // 3 jobs for name + 1 for explanation (only en) = 4
        Queue::assertPushed(GenerateTtsAudioJob::class, 4);

        Queue::assertNotPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->attribute === 'explanation_audio_url'
                && in_array($job->locale, ['uk', 'es'], true),
        );
    }

    public function test_updating_only_name_uk_dispatches_one_name_job(): void
    {
        $item = $this->makeItemSilently([
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'explanation' => ['en' => 'A', 'uk' => 'Б', 'es' => 'C'],
        ]);

        $item->setTranslation('name', 'uk', 'Новий переклад')->save();

        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->attribute === 'name_audio_url'
                && $job->locale === 'uk',
        );
    }

    public function test_updating_name_and_explanation_simultaneously_dispatches_two_jobs(): void
    {
        $item = $this->makeItemSilently([
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'explanation' => ['en' => 'Old', 'uk' => 'Б', 'es' => 'C'],
        ]);

        $item->setTranslation('name', 'uk', 'Новий переклад')
            ->setTranslation('explanation', 'en', 'New explanation')
            ->save();

        Queue::assertPushed(GenerateTtsAudioJob::class, 2);
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->attribute === 'name_audio_url'
                && $job->locale === 'uk',
        );
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->attribute === 'explanation_audio_url'
                && $job->locale === 'en',
        );
    }

    public function test_adding_new_locale_dispatches_one_job(): void
    {
        $item = $this->makeItemSilently([
            'name' => ['en' => 'Iron'],
            'explanation' => ['en' => 'Belongs in laundry'],
        ]);

        $item->setTranslation('name', 'uk', 'Праска')->save();

        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->attribute === 'name_audio_url'
                && $job->locale === 'uk',
        );
    }

    public function test_clearing_locale_dispatches_no_job(): void
    {
        $item = $this->makeItemSilently([
            'name' => ['en' => 'Iron', 'uk' => 'Праска'],
        ]);

        // Admin clears the uk translation — observer must NOT dispatch TTS
        // for an empty string. Stale audio for the old "Праска" stays in DB
        // until cleanup; this test locks in that no-dispatch behavior.
        $item->setTranslation('name', 'uk', '')->save();

        Queue::assertNothingPushed();
    }

    public function test_updating_non_translated_field_dispatches_no_jobs(): void
    {
        $item = $this->makeItemSilently();

        $item->update([
            'polygon' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        ]);

        Queue::assertNothingPushed();
    }

    public function test_updating_name_to_same_values_dispatches_no_jobs(): void
    {
        $item = $this->makeItemSilently([
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
        ]);

        $item->setTranslations('name', ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'])->save();

        Queue::assertNothingPushed();
    }

    public function test_creating_level_dispatches_one_job_per_locale(): void
    {
        $level = FindTheWrongLevel::factory()->create([
            'title' => ['en' => 'Kitchen', 'uk' => 'Кухня', 'es' => 'Cocina'],
        ]);

        Queue::assertPushed(GenerateTtsAudioJob::class, 3);

        foreach (['en', 'uk', 'es'] as $locale) {
            Queue::assertPushed(
                GenerateTtsAudioJob::class,
                fn (GenerateTtsAudioJob $job) => $job->model->is($level)
                    && $job->attribute === 'title_audio_url'
                    && $job->locale === $locale,
            );
        }
    }

    public function test_updating_level_title_uk_dispatches_one_job(): void
    {
        $level = $this->makeLevelSilently();
        $level->setTranslations('title', ['en' => 'Kitchen', 'uk' => 'Кухня', 'es' => 'Cocina']);
        $level->saveQuietly();

        $level->setTranslation('title', 'uk', 'Нова кухня')->save();

        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->attribute === 'title_audio_url'
                && $job->locale === 'uk',
        );
    }

    public function test_updating_level_image_only_dispatches_no_jobs(): void
    {
        $level = $this->makeLevelSilently();

        $level->update(['image_url' => 'games/find-the-wrong/levels/other.jpg']);

        Queue::assertNothingPushed();
    }

    /**
     * Persist an item (and its parent level) without firing any observers,
     * so subsequent `setTranslation()->save()` is the only event under test.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeItemSilently(array $attributes = []): FindTheWrongItem
    {
        $level = $this->makeLevelSilently();

        /** @var FindTheWrongItem $item */
        $item = FindTheWrongItem::withoutEvents(
            fn () => FindTheWrongItem::factory()->create([
                'level_id' => $level->id,
                ...$attributes,
            ])
        );

        return $item;
    }
}
