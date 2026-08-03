<?php

namespace Tests\Feature;

use App\Data\LlmTurnResultData;
use App\Enums\ConversationStage;
use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Exceptions\LlmUnavailableException;
use App\Filament\Pages\AiSeller;
use App\Models\Furniture;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadSubmissionService;
use App\Services\Llm\LlmClientInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Fakes\FakeLlmClient;
use Tests\TestCase;

class AiSellerPageTest extends TestCase
{
    use RefreshDatabase;

    private const GREETING = 'Здравствуйте! Я помогу подобрать мебель и оформить заявку. Что вас интересует?';

    private FakeLlmClient $llmClient;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->llmClient = new FakeLlmClient;
        $this->llmClient->result = $this->llmResult();
        app()->instance(LlmClientInterface::class, $this->llmClient);
    }

    public function test_authenticated_user_can_open_ai_seller_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/ai-seller')
            ->assertOk()
            ->assertSee('AI-продавец');
    }

    public function test_guest_cannot_open_ai_seller_page(): void
    {
        $this->get('/admin/ai-seller')
            ->assertRedirect('/admin/login');
    }

    public function test_initial_assistant_greeting_exists(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->assertSee(self::GREETING)
            ->assertSet('messages.0.role', 'assistant');
    }

    public function test_user_can_send_message(): void
    {
        $this->actingAs(User::factory()->create());
        $this->llmClient->result = $this->llmResult([
            'assistantMessage' => 'Когда планируете приобрести мебель?',
            'furnitureType' => 'wardrobe',
            'budget' => '70000',
            'roomSize' => 'спальня',
            'style' => 'modern',
        ]);

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужен современный шкаф в спальню до 70 тысяч')
            ->call('sendMessage')
            ->assertSet('message', '')
            ->assertSee('Нужен современный шкаф в спальню до 70 тысяч')
            ->assertSee('Когда планируете приобрести мебель?')
            ->assertSee('Шкаф')
            ->assertSee('70 000 ₽')
            ->assertSee('спальня')
            ->assertSee('Современный')
            ->assertSet('leadDraft.furniture_type', 'wardrobe')
            ->assertSet('leadDraft.budget', '70000')
            ->assertSet('leadDraft.room_size', 'спальня')
            ->assertSet('leadDraft.style', 'modern')
            ->assertSet('stage', ConversationStage::Checkout->value)
            ->assertCount('messages', 3);
    }

    public function test_empty_message_is_ignored(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->set('message', '   ')
            ->call('sendMessage')
            ->assertCount('messages', 1)
            ->assertSet('messages.0.content', self::GREETING);
    }

    public function test_conversation_persists_in_session(): void
    {
        $this->actingAs(User::factory()->create());
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'bed',
        ]);

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужна кровать')
            ->call('sendMessage');

        Livewire::test(AiSeller::class)
            ->assertCount('messages', 3)
            ->assertSet('messages.1.content', 'Нужна кровать')
            ->assertSee('Нужна кровать');

        $this->assertSame(
            'Нужна кровать',
            session('ai_seller.conversation.messages.1.content'),
        );
        $this->assertSame(
            'bed',
            session('ai_seller.conversation.leadDraft.furniture_type'),
        );
    }

    public function test_new_conversation_resets_state(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужна кухня')
            ->call('sendMessage')
            ->set('leadDraft', ['city' => 'Москва'])
            ->set('recommendedFurnitureIds', [1, 2])
            ->set('recommendationFingerprint', 'test-fingerprint')
            ->set('stage', ConversationStage::Checkout->value)
            ->set('readyToSubmit', true)
            ->set('submittedLeadId', 123)
            ->call('newConversation')
            ->assertSet('messages', [
                [
                    'role' => 'assistant',
                    'content' => self::GREETING,
                ],
            ])
            ->assertSet('leadDraft', [])
            ->assertSet('recommendedFurnitureIds', [])
            ->assertSet('recommendationFingerprint', null)
            ->assertSet('stage', ConversationStage::New->value)
            ->assertSet('readyToSubmit', false)
            ->assertSet('submittedLeadId', null)
            ->assertSet('message', '');

        $this->assertSame(
            ConversationStage::New->value,
            session('ai_seller.conversation.stage'),
        );
    }

    public function test_llm_error_preserves_message_and_lead_draft(): void
    {
        $this->actingAs(User::factory()->create());
        $this->llmClient->exception = new LlmUnavailableException('Test failure');

        Livewire::test(AiSeller::class)
            ->set('leadDraft', [
                'furniture_type' => 'wardrobe',
                'budget' => '70000',
            ])
            ->set('stage', ConversationStage::Qualification->value)
            ->set('message', 'Размер около двух метров')
            ->call('sendMessage')
            ->assertSet('leadDraft', [
                'furniture_type' => 'wardrobe',
                'budget' => '70000',
            ])
            ->assertSet('messages.1.content', 'Размер около двух метров')
            ->assertSet(
                'messages.2.content',
                'Сервис временно недоступен. Попробуйте отправить сообщение ещё раз.',
            );

        $this->assertSame(
            'wardrobe',
            session('ai_seller.conversation.leadDraft.furniture_type'),
        );
    }

    public function test_recommendations_use_real_ids_and_are_saved_in_session(): void
    {
        $this->actingAs(User::factory()->create());
        $wardrobe = $this->createFurniture();
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'wardrobe',
            'budget' => '70000',
            'roomSize' => 'спальня',
            'style' => 'modern',
        ]);

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужен современный шкаф в спальню до 70 тысяч')
            ->call('sendMessage')
            ->assertSet('recommendedFurnitureIds', [$wardrobe->getKey()]);

        $this->assertSame(
            [$wardrobe->getKey()],
            session('ai_seller.conversation.recommendedFurnitureIds'),
        );
    }

    public function test_ui_displays_real_recommended_furniture(): void
    {
        $this->actingAs(User::factory()->create());
        $wardrobe = $this->createFurniture();
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'wardrobe',
            'style' => 'modern',
        ]);

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужен современный шкаф')
            ->call('sendMessage')
            ->assertSee('Подходящие варианты')
            ->assertSee($wardrobe->name)
            ->assertSee('55 000 ₽')
            ->assertSee($wardrobe->room_size);
    }

    public function test_ready_to_submit_displays_submit_action(): void
    {
        $this->actingAs(User::factory()->create());
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'sofa',
            'name' => 'Иван',
            'phone' => '+7 999 123-45-67',
        ]);

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужен диван. Я Иван, +7 999 123-45-67')
            ->call('sendMessage')
            ->assertSet('readyToSubmit', true)
            ->assertSee('Отправить заявку');
    }

    public function test_successful_submit_creates_one_lead_and_marks_conversation_submitted(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->set('leadDraft', $this->validLeadDraft())
            ->set('readyToSubmit', true)
            ->set('stage', ConversationStage::Checkout->value)
            ->call('submitLead')
            ->assertSet('stage', ConversationStage::Submitted->value)
            ->assertSet('readyToSubmit', false)
            ->assertSet('messages.1.content', 'Заявка принята, менеджер свяжется с вами.');

        $this->assertDatabaseCount(Lead::class, 1);
        $this->assertNotNull(session('ai_seller.conversation.submittedLeadId'));
    }

    public function test_repeated_submit_does_not_create_second_lead(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->set('leadDraft', $this->validLeadDraft())
            ->set('readyToSubmit', true)
            ->set('stage', ConversationStage::Checkout->value)
            ->call('submitLead')
            ->call('submitLead')
            ->assertSet('stage', ConversationStage::Submitted->value);

        $this->assertDatabaseCount(Lead::class, 1);
    }

    public function test_new_conversation_after_submit_clears_submitted_state(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->set('leadDraft', $this->validLeadDraft())
            ->set('recommendedFurnitureIds', [10, 20])
            ->set('recommendationFingerprint', 'fingerprint')
            ->set('readyToSubmit', true)
            ->set('stage', ConversationStage::Checkout->value)
            ->call('submitLead')
            ->call('newConversation')
            ->assertSet('stage', ConversationStage::New->value)
            ->assertSet('leadDraft', [])
            ->assertSet('recommendedFurnitureIds', [])
            ->assertSet('recommendationFingerprint', null)
            ->assertSet('readyToSubmit', false)
            ->assertSet('submittedLeadId', null)
            ->assertSet('messages.0.content', self::GREETING)
            ->assertCount('messages', 1);
    }

    public function test_submission_error_preserves_conversation_and_allows_retry(): void
    {
        $this->actingAs(User::factory()->create());
        $leadSubmission = Mockery::mock(LeadSubmissionService::class);
        $leadSubmission
            ->shouldReceive('submit')
            ->once()
            ->andThrow(new RuntimeException('Internal database error'));
        app()->instance(LeadSubmissionService::class, $leadSubmission);

        Livewire::test(AiSeller::class)
            ->set('leadDraft', $this->validLeadDraft())
            ->set('recommendedFurnitureIds', [10])
            ->set('readyToSubmit', true)
            ->set('stage', ConversationStage::Checkout->value)
            ->call('submitLead')
            ->assertSet('leadDraft', $this->validLeadDraft())
            ->assertSet('recommendedFurnitureIds', [10])
            ->assertSet('stage', ConversationStage::Checkout->value)
            ->assertSet('readyToSubmit', true)
            ->assertSet('submittedLeadId', null)
            ->assertSet(
                'messages.1.content',
                'Не удалось отправить заявку. Пожалуйста, попробуйте ещё раз.',
            );

        $this->assertDatabaseCount(Lead::class, 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function llmResult(array $overrides = []): LlmTurnResultData
    {
        $values = [
            'assistantMessage' => 'Уточните следующий параметр.',
            'furnitureType' => null,
            'budget' => null,
            'roomSize' => null,
            'style' => null,
            'timeline' => null,
            'city' => null,
            'name' => null,
            'phone' => null,
            'catalogSearchNeeded' => false,
            'readyToSubmit' => false,
            ...$overrides,
        ];

        return new LlmTurnResultData(
            assistantMessage: $values['assistantMessage'],
            furnitureType: $values['furnitureType'],
            budget: $values['budget'],
            roomSize: $values['roomSize'],
            style: $values['style'],
            timeline: $values['timeline'],
            city: $values['city'],
            name: $values['name'],
            phone: $values['phone'],
            catalogSearchNeeded: $values['catalogSearchNeeded'],
            readyToSubmit: $values['readyToSubmit'],
        );
    }

    private function createFurniture(): Furniture
    {
        return Furniture::query()->create([
            'name' => 'Шкаф «Тестовый»',
            'type' => FurnitureType::Wardrobe,
            'price' => '55000',
            'room_size' => 'спальня, 180×60×220 см',
            'style' => FurnitureStyle::Modern,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validLeadDraft(): array
    {
        return [
            'furniture_type' => 'wardrobe',
            'budget' => '70000',
            'room_size' => 'спальня',
            'style' => 'modern',
            'timeline' => 'через месяц',
            'city' => 'Нальчик',
            'name' => 'Иван',
            'phone' => '+79991234567',
        ];
    }
}
