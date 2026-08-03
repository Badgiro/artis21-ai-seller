<?php

namespace App\Filament\Pages;

use App\Enums\ConversationStage;
use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Exceptions\LlmUnavailableException;
use App\Models\Furniture;
use App\Services\AiSellerService;
use App\Services\FurnitureRecommendationService;
use App\Services\LeadSubmissionService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class AiSeller extends Page
{
    private const SESSION_KEY = 'ai_seller.conversation';

    private const GREETING = 'Здравствуйте! Я помогу подобрать мебель и оформить заявку. Что вас интересует?';

    private const UNAVAILABLE_MESSAGE = 'Сервис временно недоступен. Попробуйте отправить сообщение ещё раз.';

    private const SUBMITTED_MESSAGE = 'Заявка принята, менеджер свяжется с вами.';

    private const SUBMISSION_ERROR_MESSAGE = 'Не удалось отправить заявку. Пожалуйста, попробуйте ещё раз.';

    protected static ?string $title = 'AI-продавец';

    protected static ?string $navigationLabel = 'AI-продавец';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'ai-seller';

    protected string $view = 'filament.pages.ai-seller';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @var array<int, array{role: string, content: string}>
     */
    public array $messages = [];

    /**
     * @var array<string, mixed>
     */
    public array $leadDraft = [];

    /**
     * @var array<int, int>
     */
    public array $recommendedFurnitureIds = [];

    public ?string $recommendationFingerprint = null;

    public ?int $submittedLeadId = null;

    public string $stage = ConversationStage::New->value;

    public bool $readyToSubmit = false;

    public string $message = '';

    public function mount(): void
    {
        $this->restoreState();
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public function sendMessage(AiSellerService $aiSeller): void
    {
        $content = trim($this->message);

        if ($content === '' || $this->stage === ConversationStage::Submitted->value) {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];
        $this->message = '';

        try {
            $turn = $aiSeller->processTurn(
                $this->messages,
                $this->leadDraft,
                $this->recommendedFurnitureIds,
                $this->recommendationFingerprint,
            );

            $this->leadDraft = $turn['leadDraft'];
            $this->stage = $turn['stage']->value;
            $this->readyToSubmit = $turn['readyToSubmit'];
            $this->recommendedFurnitureIds = $turn['recommendedFurnitureIds'];
            $this->recommendationFingerprint = $turn['recommendationFingerprint'];
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $turn['assistantMessage'],
            ];
        } catch (LlmUnavailableException) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => self::UNAVAILABLE_MESSAGE,
            ];
        }

        $this->persistState();
    }

    public function submitLead(LeadSubmissionService $leadSubmission): void
    {
        if (! $this->canSubmit()) {
            return;
        }

        try {
            $lead = $leadSubmission->submit($this->leadDraft);

            $this->submittedLeadId = $lead->getKey();
            $this->stage = ConversationStage::Submitted->value;
            $this->readyToSubmit = false;
            $this->messages[] = [
                'role' => 'assistant',
                'content' => self::SUBMITTED_MESSAGE,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $this->messages[] = [
                'role' => 'assistant',
                'content' => self::SUBMISSION_ERROR_MESSAGE,
            ];
        }

        $this->persistState();
    }

    public function newConversation(): void
    {
        session()->forget(self::SESSION_KEY);

        $this->applyState($this->initialState());
        $this->message = '';

        $this->persistState();
    }

    public function stageLabel(): string
    {
        return ConversationStage::tryFrom($this->stage)?->label() ?? '—';
    }

    public function canSubmit(): bool
    {
        return $this->readyToSubmit
            && $this->submittedLeadId === null
            && $this->stage !== ConversationStage::Submitted->value;
    }

    /**
     * @return Collection<int, Furniture>
     */
    public function recommendedFurniture(): Collection
    {
        return app(FurnitureRecommendationService::class)
            ->findByIds($this->recommendedFurnitureIds);
    }

    public function leadValue(string $key): string
    {
        $value = $this->leadDraft[$key] ?? null;

        if ($value === null || $value === '') {
            return '—';
        }

        if ($key === 'budget' && is_numeric($value)) {
            [$integer, $fraction] = array_pad(explode('.', (string) $value, 2), 2, null);
            $formatted = number_format((int) $integer, 0, ',', ' ');

            if ($fraction !== null && trim($fraction, '0') !== '') {
                $formatted .= ','.rtrim($fraction, '0');
            }

            return $formatted.' ₽';
        }

        if ($key === 'furniture_type') {
            return FurnitureType::tryFrom((string) $value)?->label() ?? '—';
        }

        if ($key === 'style') {
            return FurnitureStyle::tryFrom((string) $value)?->label() ?? '—';
        }

        return (string) $value;
    }

    private function restoreState(): void
    {
        $state = session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            $state = $this->initialState();
        }

        $this->applyState($state);
        $this->persistState();
    }

    private function persistState(): void
    {
        session()->put(self::SESSION_KEY, [
            'messages' => $this->messages,
            'leadDraft' => $this->leadDraft,
            'recommendedFurnitureIds' => $this->recommendedFurnitureIds,
            'recommendationFingerprint' => $this->recommendationFingerprint,
            'stage' => $this->stage,
            'readyToSubmit' => $this->readyToSubmit,
            'submittedLeadId' => $this->submittedLeadId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function applyState(array $state): void
    {
        $defaults = $this->initialState();

        $this->messages = is_array($state['messages'] ?? null)
            ? $state['messages']
            : $defaults['messages'];
        $this->leadDraft = is_array($state['leadDraft'] ?? null)
            ? $state['leadDraft']
            : [];
        $this->recommendedFurnitureIds = is_array($state['recommendedFurnitureIds'] ?? null)
            ? $state['recommendedFurnitureIds']
            : [];
        $this->recommendationFingerprint = is_string($state['recommendationFingerprint'] ?? null)
            ? $state['recommendationFingerprint']
            : null;
        $this->stage = ConversationStage::tryFrom((string) ($state['stage'] ?? ''))?->value
            ?? ConversationStage::New->value;
        $this->readyToSubmit = (bool) ($state['readyToSubmit'] ?? false);
        $this->submittedLeadId = is_numeric($state['submittedLeadId'] ?? null)
            ? (int) $state['submittedLeadId']
            : null;
    }

    /**
     * @return array{
     *     messages: array<int, array{role: string, content: string}>,
     *     leadDraft: array<string, mixed>,
     *     recommendedFurnitureIds: array<int, int>,
     *     recommendationFingerprint: ?string,
     *     stage: string,
     *     readyToSubmit: bool,
     *     submittedLeadId: ?int
     * }
     */
    private function initialState(): array
    {
        return [
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => self::GREETING,
                ],
            ],
            'leadDraft' => [],
            'recommendedFurnitureIds' => [],
            'recommendationFingerprint' => null,
            'stage' => ConversationStage::New->value,
            'readyToSubmit' => false,
            'submittedLeadId' => null,
        ];
    }
}
