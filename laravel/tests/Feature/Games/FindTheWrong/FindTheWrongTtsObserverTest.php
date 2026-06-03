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

    public function test_creating_item_with_all_locales_dispatches_six_jobs(): void
    {
        $level = FindTheWrongLevel::factory()->create();
        Queue::fake();

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
                    fn (GenerateTtsAudioJob $job) => $job->context->getModel()->is($item)
                        && $job->context->getAttribute() === $attribute
                        && $job->context->getLocale() === $locale,
                );
            }
        }
    }

    public function test_creating_item_skips_empty_explanation_locales(): void
    {
        $level = FindTheWrongLevel::factory()->create();
        Queue::fake();

        FindTheWrongItem::factory()->create([
            'level_id' => $level->id,
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'explanation' => ['en' => 'Belongs in laundry', 'uk' => '', 'es' => ''],
        ]);

        // 3 jobs for name + 1 for explanation (only en) = 4
        Queue::assertPushed(GenerateTtsAudioJob::class, 4);

        Queue::assertNotPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->context->getAttribute() === 'explanation_audio_url'
                && in_array($job->context->getLocale(), ['uk', 'es'], true),
        );
    }

    public function test_updating_only_name_uk_dispatches_one_name_job(): void
    {
        $item = FindTheWrongItem::factory()->create([
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'explanation' => ['en' => 'A', 'uk' => 'Б', 'es' => 'C'],
        ]);
        Queue::fake();

        $item->setTranslation('name', 'uk', 'Новий переклад')->save();

        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->context->getAttribute() === 'name_audio_url'
                && $job->context->getLocale() === 'uk',
        );
    }

    public function test_updating_non_translated_field_dispatches_no_jobs(): void
    {
        $item = FindTheWrongItem::factory()->create();
        Queue::fake();

        $item->update([
            'polygon' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        ]);

        Queue::assertNothingPushed();
    }

    public function test_updating_name_to_same_values_dispatches_no_jobs(): void
    {
        $item = FindTheWrongItem::factory()->create([
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
        ]);
        Queue::fake();

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
                fn (GenerateTtsAudioJob $job) => $job->context->getModel()->is($level)
                    && $job->context->getAttribute() === 'title_audio_url'
                    && $job->context->getLocale() === $locale,
            );
        }
    }

    public function test_updating_level_title_uk_dispatches_one_job(): void
    {
        $level = FindTheWrongLevel::factory()->create([
            'title' => ['en' => 'Kitchen', 'uk' => 'Кухня', 'es' => 'Cocina'],
        ]);
        Queue::fake();

        $level->setTranslation('title', 'uk', 'Нова кухня')->save();

        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job) => $job->context->getAttribute() === 'title_audio_url'
                && $job->context->getLocale() === 'uk',
        );
    }

    public function test_updating_level_image_only_dispatches_no_jobs(): void
    {
        $level = FindTheWrongLevel::factory()->create();
        Queue::fake();

        $level->update(['image_url' => 'games/find-the-wrong/levels/other.jpg']);

        Queue::assertNothingPushed();
    }
}
