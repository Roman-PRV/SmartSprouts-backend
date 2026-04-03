<?php

namespace Tests\Unit\Games\TrueFalseImage\Http\Resources;

use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageLevelResource;
use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageStatementResource;
use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrueFalseImageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('uk');
    }

    /** @test */
    public function true_false_image_statement_resource_disables_audio_fallback(): void
    {
        $statement = new TrueFalseImageStatement;
        $statement->setTranslation('statement', 'en', 'English Statement');
        $statement->setTranslation('statement_audio_url', 'en', 'en_audio.mp3');

        $resource = new TrueFalseImageStatementResource($statement);
        $data = $resource->toArray(request());

        // Text SHOULD fallback to English
        $this->assertEquals('English Statement', $data['statement']);

        // Audio SHOULD NOT fallback to English
        $this->assertNull($data['statement_audio_url']);
    }

    /** @test */
    public function true_false_image_level_resource_disables_audio_fallback(): void
    {
        $level = new TrueFalseImageLevel;
        $level->setTranslation('title', 'en', 'English Title');
        $level->setTranslation('title_audio_url', 'en', 'en_title_audio.mp3');

        $resource = new TrueFalseImageLevelResource($level);
        $data = $resource->toArray(request());

        // Text/Title SHOULD fallback to English
        $this->assertEquals('English Title', $data['title']);

        // Audio SHOULD NOT fallback to English
        $this->assertNull($data['title_audio_url']);
    }
}
