<?php

namespace App\Data;

final readonly class LlmTurnResultData
{
    public function __construct(
        public string $assistantMessage,
        public ?string $furnitureType,
        public ?string $budget,
        public ?string $roomSize,
        public ?string $style,
        public ?string $timeline,
        public ?string $city,
        public ?string $name,
        public ?string $phone,
        public bool $catalogSearchNeeded,
        public bool $readyToSubmit,
    ) {}
}
