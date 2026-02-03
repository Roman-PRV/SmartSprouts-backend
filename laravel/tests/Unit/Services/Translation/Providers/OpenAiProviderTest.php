<?php

namespace Tests\Unit\Services\Translation\Providers;

use App\DTO\TranslationItemDTO;
use App\DTO\TranslationResultDTO;
use App\Enums\TranslationStatusEnum;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Services\Translation\Providers\OpenAiProvider;
use Mockery;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\ChatContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * Fake client exception for testing purposes.
 */
class FakeClientException extends \Exception implements ClientExceptionInterface
{
    public function __construct(string $message = 'Test client exception')
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return Mockery::mock(RequestInterface::class);
    }
}

class OpenAiProviderTest extends TestCase
{
    private $client;

    private $chat;

    private array $locales = ['en', 'uk', 'es'];

    private string $template = 'Translate to :locales';

    private string $model = 'gpt-4o-mini';

    private OpenAiProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(ClientContract::class);
        $this->chat = Mockery::mock(ChatContract::class);

        $this->client->shouldReceive('chat')->andReturn($this->chat);

        $this->provider = new OpenAiProvider(
            $this->client,
            $this->locales,
            3,      // retry times
            0,      // retry sleep
            $this->model,
            $this->template
        );
    }

    public function test_it_translates_text_into_all_locales(): void
    {
        $text = 'Apple';
        $jsonResponse = json_encode([
            'en' => 'Apple',
            'uk' => 'Яблуко',
            'es' => 'Manzana',
        ]);

        $response = CreateResponse::from([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $this->model,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $jsonResponse,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ], MetaInformation::from([]));

        $this->chat->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $result = $this->provider->translate($text);

        $this->assertInstanceOf(TranslationResultDTO::class, $result);
        $this->assertIsString($result->requestId);
        $this->assertCount(3, $result->translations);

        $this->assertInstanceOf(TranslationItemDTO::class, $result->translations['en']);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['en']->status);
        $this->assertEquals('Apple', $result->translations['en']->text);

        $this->assertInstanceOf(TranslationItemDTO::class, $result->translations['uk']);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['uk']->status);
        $this->assertEquals('Яблуко', $result->translations['uk']->text);

        $this->assertInstanceOf(TranslationItemDTO::class, $result->translations['es']);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['es']->status);
        $this->assertEquals('Manzana', $result->translations['es']->text);
    }

    public function test_it_throws_insufficient_funds_exception_on_quota_error(): void
    {
        $text = 'Money';

        // Create ErrorException using reflection to bypass private constructor
        $reflection = new \ReflectionClass(ErrorException::class);
        $exception = $reflection->newInstanceWithoutConstructor();

        // Set private properties using reflection
        $contentsProperty = $reflection->getProperty('contents');
        $contentsProperty->setAccessible(true);
        $contentsProperty->setValue($exception, [
            'message' => 'You exceeded your current quota',
            'type' => 'insufficient_quota',
            'code' => 'insufficient_quota',
        ]);

        $this->chat->shouldReceive('create')
            ->once()
            ->andThrow($exception);

        $this->expectException(InsufficientFundsException::class);

        $this->provider->translate($text);
    }

    public function test_it_retries_on_transporter_exception_and_eventually_succeeds(): void
    {
        $text = 'Retry';
        $jsonResponse = json_encode([
            'en' => 'Retry',
            'uk' => 'Повтор',
            'es' => 'Reintentar',
        ]);

        $response = CreateResponse::from([
            'id' => 'chatcmpl-retry',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $this->model,
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $jsonResponse], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
        ], MetaInformation::from([]));

        // Create a proper ClientExceptionInterface for TransporterException
        $transporterException = new TransporterException(new FakeClientException('Connection error'));

        // Fail twice with exceptions
        $this->chat->shouldReceive('create')
            ->times(2)
            ->andThrow($transporterException);

        // Succeed on third attempt
        $this->chat->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $result = $this->provider->translate($text);

        $this->assertEquals('Повтор', $result->translations['uk']->text);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['uk']->status);
    }

    public function test_it_sanitizes_missing_locales_in_response(): void
    {
        $text = 'Invalid';
        $jsonResponse = json_encode(['en' => 'Invalid']);

        $response = CreateResponse::from([
            'id' => 'chatcmpl-invalid',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $this->model,
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $jsonResponse], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
        ], MetaInformation::from([]));

        $this->chat->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $result = $this->provider->translate($text);

        $this->assertEquals('Invalid', $result->translations['en']->text);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['en']->status);

        // Missing locales should be filled with Error status and fallback
        $this->assertArrayHasKey('uk', $result->translations);
        $this->assertEquals(TranslationStatusEnum::Error, $result->translations['uk']->status);
        $this->assertEquals($text, $result->translations['uk']->fallback);

        $this->assertArrayHasKey('es', $result->translations);
        $this->assertEquals(TranslationStatusEnum::Error, $result->translations['es']->status);
        $this->assertEquals($text, $result->translations['es']->fallback);
    }

    public function test_it_throws_exception_on_malformed_json(): void
    {
        $text = 'Test';
        $malformedJson = '{invalid json content}';

        $response = CreateResponse::from([
            'id' => 'chatcmpl-malformed',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $this->model,
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $malformedJson], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
        ], MetaInformation::from([]));

        $this->chat->shouldReceive('create')
            ->once()
            ->andReturn($response);

        $this->expectException(TranslationFailedException::class);
        $this->expectExceptionMessage('AI provider returned an invalid JSON response');

        $this->provider->translate($text);
    }

    public function test_it_throws_translation_failed_after_max_retries(): void
    {
        $text = 'Fail';

        // Create a proper ClientExceptionInterface for TransporterException
        $transporterException = new TransporterException(new FakeClientException('Persistent error'));

        $this->chat->shouldReceive('create')
            ->times(3)
            ->andThrow($transporterException);

        $this->expectException(TranslationFailedException::class);

        $this->provider->translate($text);
    }

    public function test_it_does_not_retry_on_auth_error(): void
    {
        $text = 'Secret';

        // Create ErrorException for auth error
        $reflection = new \ReflectionClass(ErrorException::class);
        $exception = $reflection->newInstanceWithoutConstructor();
        $contentsProperty = $reflection->getProperty('contents');
        $contentsProperty->setAccessible(true);
        $contentsProperty->setValue($exception, [
            'message' => 'Incorrect API key provided',
            'type' => 'invalid_request_error',
            'code' => 'invalid_api_key',
        ]);

        $this->chat->shouldReceive('create')
            ->once() // Should not retry
            ->andThrow($exception);

        $this->expectException(TranslationFailedException::class);

        $this->provider->translate($text);
    }
}
