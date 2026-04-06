<?php

namespace Tests\Unit\Games\TrueFalseText\Http\Resources;

use App\Games\TrueFalseText\Http\Resources\TrueFalseTextLevelResource;
use App\Games\TrueFalseText\Http\Resources\TrueFalseTextStatementResource;
use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use Tests\TestCase;

class TrueFalseTextResourceTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('uk');
    }

    /** @test */
    public function true_false_text_statement_resource_disables_audio_fallback(): void
    {
        $statement = new TrueFalseTextStatement;
        $statement->setTranslation('statement', 'en', 'English Statement');
        $statement->setTranslation('statement_audio_url', 'en', 'en_audio.mp3');

        $resource = new TrueFalseTextStatementResource($statement);
        $data = $resource->toArray(request());

        // Text SHOULD fallback to English
        $this->assertEquals('English Statement', $data['statement']);

        // Audio SHOULD NOT fallback to English, should be NULL
        $this->assertNull($data['statement_audio_url']);
    }

    /** @test */
    public function true_false_text_level_resource_disables_audio_fallback(): void
    {
        $level = new TrueFalseTextLevel;
        $level->setTranslation('title', 'en', 'English Title');
        $level->setTranslation('text', 'en', 'English Text');
        $level->setTranslation('title_audio_url', 'en', 'en_title_audio.mp3');
        $level->setTranslation('text_audio_url', 'en', 'en_text_audio.mp3');

        $resource = new TrueFalseTextLevelResource($level);
        $data = $resource->toArray(request());

        // Text/Title SHOULD fallback to English
        $this->assertEquals('English Title', $data['title']);
        $this->assertEquals('English Text', $data['text']);

        // Audio SHOULD NOT fallback
        $this->assertNull($data['title_audio_url']);
        $this->assertNull($data['text_audio_url']);
    }
}
