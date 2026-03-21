<?php

namespace Tests\Feature\Traits;

use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

class HasTtsAudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tts_test_models', function (Blueprint $table) {
            $table->id();
            $table->json('audio_paths')->default('{}');
            $table->string('simple_path')->nullable();
            $table->timestamps();
        });
    }

    public function test_set_audio_path_updates_only_specified_locale_without_overwriting_others()
    {
        // 1. Arrange: Create a record with an existing English audio path
        $model = TtsTestModel::forceCreate([
            'audio_paths' => [
                'en' => 'path/to/en/audio.mp3',
            ],
        ]);

        $this->assertEquals('path/to/en/audio.mp3', $model->getTranslation('audio_paths', 'en'));
        $this->assertEmpty($model->getTranslation('audio_paths', 'uk', false));

        // 2. Act: setAudioPath for Ukrainian
        $model->setAudioPath('audio_paths', 'uk', 'path/to/uk/audio.mp3');

        // 3. Assert: fetching fresh from DB should have both locales intact
        /** @var TtsTestModel $freshModel */
        $freshModel = $model->fresh();

        $this->assertEquals('path/to/en/audio.mp3', $freshModel->getTranslation('audio_paths', 'en'));
        $this->assertEquals('path/to/uk/audio.mp3', $freshModel->getTranslation('audio_paths', 'uk'));
    }

    public function test_set_audio_path_works_with_empty_default_json_object()
    {
        // 1. Arrange: rely on DB default empty JSON object `{}`
        $model = TtsTestModel::forceCreate([]);

        // 2. Act
        $model->setAudioPath('audio_paths', 'en', 'path/en.mp3');

        // 3. Assert
        $freshModel = $model->fresh();
        $this->assertEquals('path/en.mp3', $freshModel->getTranslation('audio_paths', 'en'));
    }

    public function test_set_audio_path_without_translatable_trait_falls_back_to_force_fill()
    {
        // 1. Arrange
        $model = TtsTestModelNonTranslatable::forceCreate([]);

        // 2. Act
        $model->setAudioPath('simple_path', 'en', 'simple_path.mp3');

        // 3. Assert
        $freshModel = $model->fresh();
        $this->assertEquals('simple_path.mp3', $freshModel->simple_path);
    }
}

class TtsTestModel extends Model
{
    use HasTranslations;
    use HasTtsAudio;

    protected $table = 'tts_test_models';
    protected $guarded = [];

    // Defining translatable makes Spatie package cast the column correctly automatically for the test
    public $translatable = ['audio_paths'];
    
    // Explicit array cast for Spatie < v6 / Laravel contexts
    protected $casts = [
        'audio_paths' => 'array',
    ];
}

class TtsTestModelNonTranslatable extends Model
{
    use HasTtsAudio;

    protected $table = 'tts_test_models'; // reuse the same test table
    protected $guarded = [];
}
