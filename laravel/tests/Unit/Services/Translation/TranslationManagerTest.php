<?php

namespace Tests\Unit\Services\Translation;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationItemDTO;
use App\DTO\TranslationResultDTO;
use App\Enums\TranslationStatusEnum;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Services\Translation\TranslationManager;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TranslationManagerTest extends TestCase
{
    private TranslationProviderInterface $deepLProvider;

    private TranslationProviderInterface $openAiProvider;

    private TranslationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deepLProvider = Mockery::mock(TranslationProviderInterface::class);
        $this->openAiProvider = Mockery::mock(TranslationProviderInterface::class);

        $this->manager = new TranslationManager(
            $this->deepLProvider,
            $this->openAiProvider
        );
    }

    public function test_it_returns_deepl_translation_on_success(): void
    {
        // Arrange
        $text = 'Hello world';
        $expectedResult = new TranslationResultDTO(
            translations: [
                'uk' => new TranslationItemDTO(TranslationStatusEnum::Success, 'Привіт світ'),
                'es' => new TranslationItemDTO(TranslationStatusEnum::Success, 'Hola mundo'),
            ],
            requestId: 'test-request-id'
        );

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andReturn($expectedResult);

        $this->openAiProvider
            ->shouldNotReceive('translate');

        // Act
        $result = $this->manager->translate($text);

        // Assert
        $this->assertSame($expectedResult, $result);
        $this->assertCount(2, $result->translations);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['uk']->status);
        $this->assertEquals('Привіт світ', $result->translations['uk']->text);
    }

    public function test_it_falls_back_to_openai_when_deepl_throws_insufficient_funds(): void
    {
        // Arrange
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'DeepL failed, switching to OpenAI')
                    && isset($context['error'])
                    && isset($context['exception_type'])
                    && isset($context['text_hash'])
                    && isset($context['text_length']);
            });

        $text = 'Hello world';
        $exception = new InsufficientFundsException;

        $fallbackResult = new TranslationResultDTO(
            translations: [
                'uk' => new TranslationItemDTO(TranslationStatusEnum::Success, 'Привіт світ (OpenAI)'),
            ],
            requestId: 'openai-request-id'
        );

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andThrow($exception);

        $this->openAiProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andReturn($fallbackResult);

        // Act
        $result = $this->manager->translate($text);

        // Assert
        $this->assertSame($fallbackResult, $result);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['uk']->status);
    }

    public function test_it_falls_back_to_openai_when_deepl_throws_translation_failed(): void
    {
        // Arrange
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return isset($context['error'])
                    && isset($context['exception_type']);
            });

        $text = 'Test text';
        $exception = new TranslationFailedException('DeepL API timeout');

        $fallbackResult = new TranslationResultDTO(
            translations: [
                'uk' => new TranslationItemDTO(TranslationStatusEnum::Success, 'Тестовий текст'),
            ],
            requestId: 'test-id'
        );

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andThrow($exception);

        $this->openAiProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andReturn($fallbackResult);

        // Act
        $result = $this->manager->translate($text);

        // Assert
        $this->assertSame($fallbackResult, $result);
    }

    public function test_it_wraps_non_whitelisted_exceptions_in_translation_failed_exception(): void
    {
        // Arrange
        $text = 'Test';
        $originalException = new \InvalidArgumentException('Invalid input data');

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andThrow($originalException);

        $this->openAiProvider
            ->shouldNotReceive('translate');

        // Act & Assert
        try {
            $this->manager->translate($text);
            $this->fail('Expected TranslationFailedException to be thrown');
        } catch (TranslationFailedException $e) {
            $this->assertEquals(__('exceptions.translation.unexpected_exception'), $e->getMessage());
            $this->assertSame($originalException, $e->getPrevious());
            $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function test_it_propagates_openai_exception_when_both_providers_fail(): void
    {
        // Arrange
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return isset($context['error'])
                    && isset($context['exception_type']);
            });

        $text = 'Test';
        $deepLException = new TranslationFailedException('DeepL failed');
        $openAiException = new InsufficientFundsException;

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andThrow($deepLException);

        $this->openAiProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andThrow($openAiException);

        // Assert
        $this->expectException(InsufficientFundsException::class);

        // Act
        $this->manager->translate($text);
    }

    public function test_it_does_not_fall_back_on_non_recoverable_exception(): void
    {
        // Arrange
        $text = 'Test';
        $exception = new TranslationFailedException(
            message: 'Provider misconfigured',
            shouldFailover: false
        );

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->with($text)
            ->andThrow($exception);

        $this->openAiProvider
            ->shouldNotReceive('translate');

        // Assert
        $this->expectException(TranslationFailedException::class);
        $this->expectExceptionMessage('Provider misconfigured');

        // Act
        $this->manager->translate($text);
    }

    public function test_it_logs_text_hash_and_length_during_failover(): void
    {
        // Arrange
        $text = 'Sensitive data that should not be logged';
        $expectedHash = hash('sha256', $text);
        $expectedLength = mb_strlen($text);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($expectedHash, $expectedLength) {
                return $context['text_hash'] === $expectedHash
                    && $context['text_length'] === $expectedLength
                    && isset($context['error'])
                    && isset($context['exception_type']);
            });

        $this->deepLProvider
            ->shouldReceive('translate')
            ->once()
            ->andThrow(new TranslationFailedException('Error'));

        $this->openAiProvider
            ->shouldReceive('translate')
            ->once()
            ->andReturn(new TranslationResultDTO(
                translations: [],
                requestId: 'test-id'
            ));

        // Act
        $this->manager->translate($text);
    }

    public function test_get_name_returns_manager(): void
    {
        // Act
        $name = $this->manager->getName();

        // Assert
        $this->assertEquals('manager', $name);
    }
}
