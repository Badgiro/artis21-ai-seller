<?php

namespace Tests\Fakes;

use App\Data\LlmTurnResultData;
use App\Exceptions\LlmUnavailableException;
use App\Services\Llm\LlmClientInterface;
use LogicException;

class FakeLlmClient implements LlmClientInterface
{
    public ?LlmTurnResultData $result = null;

    public ?LlmUnavailableException $exception = null;

    public ?string $systemPrompt = null;

    /**
     * @var array<int, array{role: string, content: string}>
     */
    public array $messages = [];

    /**
     * @var array<string, mixed>
     */
    public array $schema = [];

    public function complete(
        string $systemPrompt,
        array $messages,
        array $schema,
    ): LlmTurnResultData {
        $this->systemPrompt = $systemPrompt;
        $this->messages = $messages;
        $this->schema = $schema;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result ?? throw new LogicException('Fake LLM result is not configured.');
    }
}
