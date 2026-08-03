<?php

namespace Tests\Unit;

use App\Data\LlmTurnResultData;
use App\Exceptions\LlmUnavailableException;
use App\Services\Llm\DeepSeekLlmClient;
use App\Services\Llm\LlmClientInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DeepSeekLlmClientTest extends TestCase
{
    private const API_KEY = 'test-deepseek-key';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'llm.provider' => 'deepseek',
            'llm.deepseek.api_key' => self::API_KEY,
            'llm.deepseek.base_url' => 'https://api.deepseek.test',
            'llm.deepseek.model' => 'deepseek-v4-flash',
            'llm.timeout' => 30,
        ]);

        Http::preventStrayRequests();
    }

    public function test_successful_structured_response_returns_dto(): void
    {
        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode($this->validResult()),
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->client()->complete(
            'Return lead qualification data.',
            [['role' => 'user', 'content' => 'Нужен диван до 100000 рублей']],
            $this->schema(),
        );

        $this->assertInstanceOf(LlmTurnResultData::class, $result);
        $this->assertSame('Подберу подходящие диваны.', $result->assistantMessage);
        $this->assertSame('sofa', $result->furnitureType);
        $this->assertSame('100000.00', $result->budget);
        $this->assertTrue($result->catalogSearchNeeded);
        $this->assertFalse($result->readyToSubmit);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://api.deepseek.test/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer '.self::API_KEY)
                && $body['model'] === 'deepseek-v4-flash'
                && $body['response_format'] === ['type' => 'json_object']
                && str_contains($body['messages'][0]['content'], 'Return JSON only');
        });
    }

    public function test_missing_api_key_throws_controlled_exception(): void
    {
        config()->set('llm.deepseek.api_key', '');

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM API key is not configured.');

        try {
            $this->callClient();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_rate_limit_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Rate limited']], 429),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM rate limit exceeded.');

        $this->callClient();
    }

    public function test_server_error_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Internal error']], 500),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM API returned an unsuccessful response.');

        $this->callClient();
    }

    public function test_client_error_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Bad request']], 400),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM API returned an unsuccessful response.');

        $this->callClient();
    }

    public function test_connection_error_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::failedConnection('Connection timed out'),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM connection failed or timed out.');

        $this->callClient();
    }

    public function test_empty_response_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM API returned an empty response.');

        $this->callClient();
    }

    public function test_invalid_json_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"assistant_message":',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM structured content is invalid JSON.');

        $this->callClient();
    }

    public function test_valid_json_with_invalid_structure_throws_controlled_exception(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'assistant_message' => 'Ответ без остальных полей',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->expectException(LlmUnavailableException::class);
        $this->expectExceptionMessage('LLM structured content failed validation.');

        $this->callClient();
    }

    public function test_api_key_is_not_written_to_log(): void
    {
        Log::spy();
        Http::fake([
            '*' => Http::response(['error' => ['message' => self::API_KEY]], 500),
        ]);

        try {
            $this->callClient();
        } catch (LlmUnavailableException) {
            // The controlled failure is expected in this logging test.
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $loggedData = $message.json_encode($context);

                return ! str_contains($loggedData, self::API_KEY)
                    && $context === [
                        'provider' => 'deepseek',
                        'http_status' => 500,
                    ];
            });
    }

    public function test_container_resolves_interface_to_deepseek_client(): void
    {
        $this->assertInstanceOf(
            DeepSeekLlmClient::class,
            app(LlmClientInterface::class),
        );
    }

    private function callClient(): LlmTurnResultData
    {
        return $this->client()->complete(
            'Return test data.',
            [['role' => 'user', 'content' => 'Тест']],
            $this->schema(),
        );
    }

    private function client(): DeepSeekLlmClient
    {
        return app(DeepSeekLlmClient::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validResult(): array
    {
        return [
            'assistant_message' => 'Подберу подходящие диваны.',
            'furniture_type' => 'sofa',
            'budget' => '100000.00',
            'room_size' => null,
            'style' => null,
            'timeline' => null,
            'city' => null,
            'name' => null,
            'phone' => null,
            'catalog_search_needed' => true,
            'ready_to_submit' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'assistant_message' => ['type' => 'string'],
                'furniture_type' => ['type' => ['string', 'null']],
                'budget' => ['type' => ['string', 'null']],
                'room_size' => ['type' => ['string', 'null']],
                'style' => ['type' => ['string', 'null']],
                'timeline' => ['type' => ['string', 'null']],
                'city' => ['type' => ['string', 'null']],
                'name' => ['type' => ['string', 'null']],
                'phone' => ['type' => ['string', 'null']],
                'catalog_search_needed' => ['type' => 'boolean'],
                'ready_to_submit' => ['type' => 'boolean'],
            ],
            'required' => [
                'assistant_message',
                'furniture_type',
                'budget',
                'room_size',
                'style',
                'timeline',
                'city',
                'name',
                'phone',
                'catalog_search_needed',
                'ready_to_submit',
            ],
            'additionalProperties' => false,
        ];
    }
}
