<?php

namespace App\Services;

use App\Data\LlmTurnResultData;
use App\Enums\ConversationStage;
use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Services\Llm\LlmClientInterface;

class AiSellerService
{
    private const INVALID_PHONE_MESSAGE = 'Пожалуйста, укажите корректный номер телефона в международном формате: от 10 до 15 цифр.';

    /**
     * @var array<int, string>
     */
    private const LEAD_FIELDS = [
        'furniture_type',
        'budget',
        'room_size',
        'style',
        'timeline',
        'city',
        'name',
        'phone',
    ];

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly PhoneValidator $phoneValidator,
        private readonly FurnitureRecommendationService $recommendationService,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $currentLeadDraft
     * @param  array<int, int>  $currentRecommendationIds
     * @return array{
     *     assistantMessage: string,
     *     leadDraft: array<string, string>,
     *     stage: ConversationStage,
     *     catalogSearchNeeded: bool,
     *     readyToSubmit: bool,
     *     recommendedFurnitureIds: array<int, int>,
     *     recommendationFingerprint: ?string
     * }
     */
    public function processTurn(
        array $messages,
        array $currentLeadDraft,
        array $currentRecommendationIds = [],
        ?string $currentRecommendationFingerprint = null,
    ): array {
        $currentLeadDraft = $this->sanitizeDraft($currentLeadDraft);

        $result = $this->llmClient->complete(
            $this->systemPrompt($currentLeadDraft),
            $messages,
            $this->responseSchema(),
        );

        [$leadDraft, $invalidPhoneReceived] = $this->mergeLeadDraft(
            $currentLeadDraft,
            $result,
        );

        $catalogSearchNeeded = $this->shouldSearchCatalog($leadDraft);
        $recommendationFingerprint = $catalogSearchNeeded
            ? $this->recommendationFingerprint($leadDraft)
            : null;
        $recommendedFurnitureIds = $currentRecommendationIds;

        if (! $catalogSearchNeeded) {
            $recommendedFurnitureIds = [];
        } elseif ($recommendationFingerprint !== $currentRecommendationFingerprint) {
            $recommendedFurnitureIds = $this->recommendationService
                ->recommend(
                    $leadDraft['furniture_type'],
                    $leadDraft['budget'] ?? null,
                    $leadDraft['style'] ?? null,
                )
                ->modelKeys();
        }

        return [
            'assistantMessage' => $invalidPhoneReceived
                ? self::INVALID_PHONE_MESSAGE
                : $result->assistantMessage,
            'leadDraft' => $leadDraft,
            'stage' => $this->determineStage($leadDraft),
            'catalogSearchNeeded' => $catalogSearchNeeded,
            'readyToSubmit' => $this->isReadyToSubmit($leadDraft),
            'recommendedFurnitureIds' => $recommendedFurnitureIds,
            'recommendationFingerprint' => $recommendationFingerprint,
        ];
    }

    /**
     * @param  array<string, string>  $currentLeadDraft
     */
    private function systemPrompt(array $currentLeadDraft): string
    {
        $draftJson = json_encode(
            $currentLeadDraft,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return <<<PROMPT
        Ты AI-продавец мебельной фабрики. Проведи клиента по сценарию подбора мебели.

        Разрешённые типы мебели:
        - kitchen = кухня
        - wardrobe = шкаф
        - sofa = диван
        - bed = кровать

        Разрешённые стили:
        - modern = современный
        - classic = классика
        - loft = лофт
        - minimalism = минимализм

        Последовательно собери: furniture_type, room_size, style, budget, timeline, city, name, phone.
        Клиент может сообщить несколько значений одним сообщением — извлеки их одновременно.
        Например, «Нужен современный шкаф в спальню до 70 тысяч» означает:
        furniture_type=wardrobe, room_size=спальня, style=modern, budget=70000.

        Не спрашивай повторно уже известные значения. Если клиент явно исправляет значение,
        верни новое значение. Для неизменившихся или не упомянутых полей верни null.
        Задавай только один следующий наиболее важный вопрос в порядке:
        furniture_type, room_size, style, budget, timeline, city, name, phone.

        Если клиент уходит от темы, коротко верни его к подбору мебели.
        Не поддерживай посторонний разговор.
        Не придумывай товары или названия мебели. Реальных рекомендаций на этом шаге нет.
        Если данных достаточно для поиска каталога, установи catalog_search_needed=true
        и ответь нейтрально, без названий товаров.

        budget возвращай как неотрицательную числовую строку без обозначения валюты.
        Все поля JSON обязательны; неизвестные lead-поля возвращай как null.

        Текущее подтверждённое состояние лида:
        {$draftJson}
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'assistant_message' => ['type' => 'string'],
                'furniture_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['kitchen', 'wardrobe', 'sofa', 'bed', null],
                ],
                'budget' => [
                    'type' => ['string', 'null'],
                    'pattern' => '^\d+(?:\.\d+)?$',
                ],
                'room_size' => ['type' => ['string', 'null']],
                'style' => [
                    'type' => ['string', 'null'],
                    'enum' => ['modern', 'classic', 'loft', 'minimalism', null],
                ],
                'timeline' => ['type' => ['string', 'null']],
                'city' => ['type' => ['string', 'null']],
                'name' => ['type' => ['string', 'null']],
                'phone' => ['type' => ['string', 'null']],
                'catalog_search_needed' => ['type' => 'boolean'],
                'ready_to_submit' => ['type' => 'boolean'],
            ],
            'required' => [
                'assistant_message',
                ...self::LEAD_FIELDS,
                'catalog_search_needed',
                'ready_to_submit',
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, string>  $currentLeadDraft
     * @return array{array<string, string>, bool}
     */
    private function mergeLeadDraft(
        array $currentLeadDraft,
        LlmTurnResultData $result,
    ): array {
        $phoneWasProvided = $result->phone !== null && trim($result->phone) !== '';
        $normalizedPhone = $this->phoneValidator->normalize($result->phone);

        $newValues = [
            'furniture_type' => FurnitureType::tryFrom((string) $result->furnitureType)?->value,
            'budget' => $this->normalizeBudget($result->budget),
            'room_size' => $this->normalizeText($result->roomSize),
            'style' => FurnitureStyle::tryFrom((string) $result->style)?->value,
            'timeline' => $this->normalizeText($result->timeline),
            'city' => $this->normalizeText($result->city),
            'name' => $this->normalizeText($result->name),
            'phone' => $normalizedPhone,
        ];

        foreach ($newValues as $key => $value) {
            if ($value !== null) {
                $currentLeadDraft[$key] = $value;
            }
        }

        return [$currentLeadDraft, $phoneWasProvided && $normalizedPhone === null];
    }

    /**
     * @param  array<string, mixed>  $leadDraft
     * @return array<string, string>
     */
    private function sanitizeDraft(array $leadDraft): array
    {
        $sanitized = [];

        $values = [
            'furniture_type' => FurnitureType::tryFrom((string) ($leadDraft['furniture_type'] ?? ''))?->value,
            'budget' => $this->normalizeBudget($leadDraft['budget'] ?? null),
            'room_size' => $this->normalizeText($leadDraft['room_size'] ?? null),
            'style' => FurnitureStyle::tryFrom((string) ($leadDraft['style'] ?? ''))?->value,
            'timeline' => $this->normalizeText($leadDraft['timeline'] ?? null),
            'city' => $this->normalizeText($leadDraft['city'] ?? null),
            'name' => $this->normalizeText($leadDraft['name'] ?? null),
            'phone' => $this->phoneValidator->normalize(
                is_string($leadDraft['phone'] ?? null) ? $leadDraft['phone'] : null,
            ),
        ];

        foreach ($values as $key => $value) {
            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function normalizeBudget(mixed $budget): ?string
    {
        if (! is_string($budget)) {
            return null;
        }

        $budget = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($budget));

        if (preg_match('/^\d+(?:\.\d+)?$/', $budget) !== 1) {
            return null;
        }

        [$integer, $fraction] = array_pad(explode('.', $budget, 2), 2, null);
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = $fraction === null ? null : rtrim($fraction, '0');

        return $fraction === null || $fraction === ''
            ? $integer
            : $integer.'.'.$fraction;
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, string>  $leadDraft
     */
    private function determineStage(array $leadDraft): ConversationStage
    {
        if (! isset($leadDraft['furniture_type'])) {
            return ConversationStage::New;
        }

        $hasCheckoutData = collect(['timeline', 'city', 'name', 'phone'])
            ->contains(fn (string $field): bool => isset($leadDraft[$field]));
        $hasFurnitureParameters = collect(['room_size', 'style', 'budget'])
            ->every(fn (string $field): bool => isset($leadDraft[$field]));

        if ($hasCheckoutData || $hasFurnitureParameters) {
            return ConversationStage::Checkout;
        }

        return ConversationStage::Qualification;
    }

    /**
     * @param  array<string, string>  $leadDraft
     */
    private function isReadyToSubmit(array $leadDraft): bool
    {
        return isset($leadDraft['furniture_type'], $leadDraft['name'], $leadDraft['phone'])
            && $this->phoneValidator->isValid($leadDraft['phone']);
    }

    /**
     * @param  array<string, string>  $leadDraft
     */
    private function shouldSearchCatalog(array $leadDraft): bool
    {
        if (! isset($leadDraft['furniture_type'])) {
            return false;
        }

        return collect(['budget', 'style', 'room_size'])
            ->contains(fn (string $field): bool => isset($leadDraft[$field]));
    }

    /**
     * @param  array<string, string>  $leadDraft
     */
    private function recommendationFingerprint(array $leadDraft): string
    {
        return hash('sha256', json_encode([
            'furniture_type' => $leadDraft['furniture_type'],
            'budget' => $leadDraft['budget'] ?? null,
            'style' => $leadDraft['style'] ?? null,
            'room_size' => $leadDraft['room_size'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }
}
