<?php

namespace App\Services\Llm;

use App\Data\LlmTurnResultData;

interface LlmClientInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     */
    public function complete(
        string $systemPrompt,
        array $messages,
        array $schema,
    ): LlmTurnResultData;
}
