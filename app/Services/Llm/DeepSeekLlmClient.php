<?php

namespace App\Services\Llm;

use App\Data\LlmTurnResultData;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class DeepSeekLlmClient implements LlmClientInterface
{
    private const PROVIDER = 'deepseek';

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     */
    public function complete(
        string $systemPrompt,
        array $messages,
        array $schema,
    ): LlmTurnResultData {
        $apiKey = (string) config('llm.deepseek.api_key');

        if ($apiKey === '') {
            throw $this->unavailable('LLM API key is not configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('llm.timeout', 30))
                ->post($this->endpoint(), [
                    'model' => (string) config('llm.deepseek.model'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->structuredSystemPrompt($systemPrompt, $schema),
                        ],
                        ...$messages,
                    ],
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                    'max_tokens' => 1200,
                ]);
        } catch (ConnectionException $exception) {
            throw $this->unavailable(
                'LLM connection failed or timed out.',
                previous: $exception,
            );
        }

        $this->ensureSuccessful($response);

        $content = $this->extractContent($response);
        $data = $this->decodeStructuredContent($content);

        return $this->toData($data);
    }

    private function endpoint(): string
    {
        return rtrim((string) config('llm.deepseek.base_url'), '/').'/chat/completions';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function structuredSystemPrompt(string $systemPrompt, array $schema): string
    {
        try {
            $encodedSchema = json_encode(
                $schema,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw $this->unavailable(
                'LLM response schema could not be encoded.',
                previous: $exception,
            );
        }

        return trim($systemPrompt)."\n\n"
            .'Return JSON only. The JSON object must match this schema: '
            .$encodedSchema;
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->status() === 429) {
            throw $this->unavailable(
                'LLM rate limit exceeded.',
                status: $response->status(),
            );
        }

        if ($response->failed()) {
            throw $this->unavailable(
                'LLM API returned an unsuccessful response.',
                status: $response->status(),
            );
        }
    }

    private function extractContent(Response $response): string
    {
        if (trim($response->body()) === '') {
            throw $this->unavailable(
                'LLM API returned an empty response.',
                status: $response->status(),
            );
        }

        try {
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->unavailable(
                'LLM API returned invalid JSON.',
                status: $response->status(),
                previous: $exception,
            );
        }

        $content = data_get($payload, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw $this->unavailable(
                'LLM API returned empty structured content.',
                status: $response->status(),
            );
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStructuredContent(string $content): array
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->unavailable(
                'LLM structured content is invalid JSON.',
                previous: $exception,
            );
        }

        if (! is_array($data)) {
            throw $this->unavailable('LLM structured content must be a JSON object.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toData(array $data): LlmTurnResultData
    {
        try {
            $validated = Validator::make(
                ['result' => $data],
                [
                    'result' => [
                        'required',
                        'array:assistant_message,furniture_type,budget,room_size,style,timeline,city,name,phone,catalog_search_needed,ready_to_submit',
                    ],
                    'result.assistant_message' => ['required', 'string', 'min:1'],
                    'result.furniture_type' => ['present', 'nullable', 'string'],
                    'result.budget' => ['present', 'nullable', 'string'],
                    'result.room_size' => ['present', 'nullable', 'string'],
                    'result.style' => ['present', 'nullable', 'string'],
                    'result.timeline' => ['present', 'nullable', 'string'],
                    'result.city' => ['present', 'nullable', 'string'],
                    'result.name' => ['present', 'nullable', 'string'],
                    'result.phone' => ['present', 'nullable', 'string'],
                    'result.catalog_search_needed' => ['required', 'boolean'],
                    'result.ready_to_submit' => ['required', 'boolean'],
                ],
            )->validate();
        } catch (ValidationException $exception) {
            throw $this->unavailable(
                'LLM structured content failed validation.',
                previous: $exception,
            );
        }

        $result = $validated['result'];

        return new LlmTurnResultData(
            assistantMessage: $result['assistant_message'],
            furnitureType: $result['furniture_type'],
            budget: $result['budget'],
            roomSize: $result['room_size'],
            style: $result['style'],
            timeline: $result['timeline'],
            city: $result['city'],
            name: $result['name'],
            phone: $result['phone'],
            catalogSearchNeeded: $result['catalog_search_needed'],
            readyToSubmit: $result['ready_to_submit'],
        );
    }

    private function unavailable(
        string $message,
        ?int $status = null,
        ?Throwable $previous = null,
    ): LlmUnavailableException {
        Log::warning($message, [
            'provider' => (string) config('llm.provider', self::PROVIDER),
            'http_status' => $status,
        ]);

        return new LlmUnavailableException($message, previous: $previous);
    }
}
