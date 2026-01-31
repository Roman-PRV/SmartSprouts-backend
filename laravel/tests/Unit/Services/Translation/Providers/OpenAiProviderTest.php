<?php

namespace Tests\Unit\Services\Translation\Providers;

use App\DTO\TranslationResult;
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

        $this->assertInstanceOf(TranslationResult::class, $result);
        $this->assertEquals([
            'en' => 'Apple',
            'uk' => 'Яблуко',
            'es' => 'Manzana',
        ], $result->translations);
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

        $this->chat->shouldReceive('create')
            ->times(3)
            ->andThrowExceptions([
                $transporterException,
                $transporterException,
                $response,
            ]);

        $result = $this->provider->translate($text);

        $this->assertEquals('Повтор', $result->translations['uk']);
    }

    public function test_it_handles_invalid_json_by_sanitizing_results(): void
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

        $this->assertEquals('Invalid', $result->translations['en']);
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
}
