<?php

namespace App\Filament\Pages;

use App\Enums\ConversationStage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class AiSeller extends Page
{
    private const SESSION_KEY = 'ai_seller.conversation';

    private const GREETING = 'Здравствуйте! Я помогу подобрать мебель и оформить заявку. Что вас интересует?';

    private const FAKE_RESPONSE = 'Сообщение получено. AI-интеграция будет подключена на следующем этапе.';

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

    public string $stage = ConversationStage::New->value;

    public bool $readyToSubmit = false;

    public string $message = '';

    public function mount(): void
    {
        $this->restoreState();
    }

    public function sendMessage(): void
    {
        $content = trim($this->message);

        if ($content === '') {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];
        $this->message = '';

        $this->appendTemporaryAssistantResponse();
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

    public function leadValue(string $key): string
    {
        $value = $this->leadDraft[$key] ?? null;

        if ($value === null || $value === '') {
            return '—';
        }

        if ($key === 'budget' && is_numeric($value)) {
            return number_format((float) $value, 2, ',', ' ').' ₽';
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

    /**
     * This temporary behavior is intentionally isolated for replacement by the
     * real AI integration at the next stage.
     */
    private function appendTemporaryAssistantResponse(): void
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => self::FAKE_RESPONSE,
        ];
    }

    private function persistState(): void
    {
        session()->put(self::SESSION_KEY, [
            'messages' => $this->messages,
            'leadDraft' => $this->leadDraft,
            'recommendedFurnitureIds' => $this->recommendedFurnitureIds,
            'stage' => $this->stage,
            'readyToSubmit' => $this->readyToSubmit,
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
        $this->stage = ConversationStage::tryFrom((string) ($state['stage'] ?? ''))?->value
            ?? ConversationStage::New->value;
        $this->readyToSubmit = (bool) ($state['readyToSubmit'] ?? false);
    }

    /**
     * @return array{
     *     messages: array<int, array{role: string, content: string}>,
     *     leadDraft: array<string, mixed>,
     *     recommendedFurnitureIds: array<int, int>,
     *     stage: string,
     *     readyToSubmit: bool
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
            'stage' => ConversationStage::New->value,
            'readyToSubmit' => false,
        ];
    }
}
