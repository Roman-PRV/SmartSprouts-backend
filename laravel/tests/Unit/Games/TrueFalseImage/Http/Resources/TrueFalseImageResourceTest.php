<?php

namespace Tests\Unit\Games\TrueFalseImage\Http\Resources;

use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageStatementResource;
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
    public function true_false_image_statement_resource_disables_audio_fallback()
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
}
