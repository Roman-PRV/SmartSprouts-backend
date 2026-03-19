<?php

namespace Tests\Unit\Services\Tts\Providers;

use App\Contracts\TtsProviderInterface;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use App\Services\Tts\Providers\LocaleDispatchingTtsProvider;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LocaleDispatchingTtsProviderTest extends TestCase
{
    private TtsProviderInterface&MockInterface $kokoro;

    private TtsProviderInterface&MockInterface $ukrainian;

    private TtsProviderInterface&MockInterface $elevenlabs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kokoro = Mockery::mock(TtsProviderInterface::class);
        $this->ukrainian = Mockery::mock(TtsProviderInterface::class);
        $this->elevenlabs = Mockery::mock(TtsProviderInterface::class);

        $this->kokoro->allows('getName')->andReturn('kokoro');
        $this->ukrainian->allows('getName')->andReturn('ukrainian_tts');
        $this->elevenlabs->allows('getName')->andReturn('elevenlabs');
    }

    // ──────────────────────────────────────────────
    // Dispatch by locale
    // ──────────────────────────────────────────────

    public function test_it_dispatches_to_kokoro_for_english_locale(): void
    {
        $provider = $this->makeDispatcher();
        $request = new TtsRequestDTO(text: 'Hello', locale: 'en');
        $expected = $this->makeFakeResult();

        $this->kokoro->expects('synthesize')->with($request)->andReturn($expected);

        $result = $provider->synthesize($request);

        $this->assertSame($expected, $result);
    }

    public function test_it_dispatches_to_kokoro_for_spanish_locale(): void
    {
        $provider = $this->makeDispatcher();
        $request = new TtsRequestDTO(text: 'Hola', locale: 'es');
        $expected = $this->makeFakeResult();

        $this->kokoro->expects('synthesize')->with($request)->andReturn($expected);

        $result = $provider->synthesize($request);

        $this->assertSame($expected, $result);
    }

    public function test_it_dispatches_to_ukrainian_tts_for_ukrainian_locale(): void
    {
        $provider = $this->makeDispatcher();
        $request = new TtsRequestDTO(text: 'Привіт', locale: 'uk');
        $expected = $this->makeFakeResult();

        $this->ukrainian->expects('synthesize')->with($request)->andReturn($expected);

        $result = $provider->synthesize($request);

        $this->assertSame($expected, $result);
    }

    // public function test_it_falls_back_to_fallback_provider_for_unknown_locale(): void
    // {
    //     $logSpy = Log::spy();
    //     $provider = $this->makeDispatcher();
    //     $request = new TtsRequestDTO(text: 'Bonjour', locale: 'fr');
    //     $expected = $this->makeFakeResult();

    //     $this->elevenlabs->expects('synthesize')->with($request)->andReturn($expected);

    //     $result = $provider->synthesize($request);

    //     $this->assertSame($expected, $result);
    //     $logSpy->shouldHaveReceived('warning')->once();
    // }

    public function test_it_falls_back_to_fallback_provider_for_unknown_locale(): void
    {
        Log::shouldReceive('warning')->once();

        $provider = $this->makeDispatcher();
        $request = new TtsRequestDTO(text: 'Bonjour', locale: 'fr');
        $expected = $this->makeFakeResult();

        $this->elevenlabs->expects('synthesize')->with($request)->andReturn($expected);

        $result = $provider->synthesize($request);

        $this->assertSame($expected, $result);
    }

    public function test_it_falls_back_to_fallback_provider_when_locale_is_null(): void
    {
        $provider = $this->makeDispatcher();
        $request = new TtsRequestDTO(text: 'No locale');
        $expected = $this->makeFakeResult();

        $this->elevenlabs->expects('synthesize')->with($request)->andReturn($expected);

        $result = $provider->synthesize($request);

        $this->assertSame($expected, $result);
    }

    // ──────────────────────────────────────────────
    // Metadata
    // ──────────────────────────────────────────────

    public function test_it_returns_locale_dispatch_as_name(): void
    {
        $this->assertEquals('locale_dispatch', $this->makeDispatcher()->getName());
    }

    public function test_it_returns_merged_voices_from_all_providers(): void
    {
        $this->kokoro->allows('getAvailableVoices')->andReturn([
            'af_heart' => ['name' => 'Heart', 'language' => 'en-us', 'gender' => 'female'],
        ]);
        $this->ukrainian->allows('getAvailableVoices')->andReturn([
            'lada' => ['name' => 'Лада', 'language' => 'uk', 'gender' => 'female'],
        ]);
        // elevenlabs is the fallback — same instance as kokoro would be deduplicated
        $this->elevenlabs->allows('getAvailableVoices')->andReturn([
            'some_voice' => ['name' => 'Some', 'language' => 'en', 'gender' => 'male'],
        ]);

        $voices = $this->makeDispatcher()->getAvailableVoices();

        $this->assertArrayHasKey('af_heart', $voices);
        $this->assertArrayHasKey('lada', $voices);
        $this->assertArrayHasKey('some_voice', $voices);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function makeDispatcher(): LocaleDispatchingTtsProvider
    {
        return new LocaleDispatchingTtsProvider(
            localeMap: [
                'en' => $this->kokoro,
                'es' => $this->kokoro,
                'uk' => $this->ukrainian,
            ],
            fallback: $this->elevenlabs,
        );
    }

    private function makeFakeResult(): TtsResultDTO
    {
        return new TtsResultDTO(
            audioData: 'fake-audio',
            format: 'mp3',
            requestId: null,
        );
    }
}
