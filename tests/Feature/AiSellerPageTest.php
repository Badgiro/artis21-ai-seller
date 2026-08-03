<?php

namespace Tests\Feature;

use App\Enums\ConversationStage;
use App\Filament\Pages\AiSeller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiSellerPageTest extends TestCase
{
    use RefreshDatabase;

    private const GREETING = 'Здравствуйте! Я помогу подобрать мебель и оформить заявку. Что вас интересует?';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
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

        Livewire::test(AiSeller::class)
            ->set('message', 'Мне нужен диван')
            ->call('sendMessage')
            ->assertSet('message', '')
            ->assertSee('Мне нужен диван')
            ->assertSee('Сообщение получено. AI-интеграция будет подключена на следующем этапе.')
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
    }

    public function test_new_conversation_resets_state(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(AiSeller::class)
            ->set('message', 'Нужна кухня')
            ->call('sendMessage')
            ->set('leadDraft', ['city' => 'Москва'])
            ->set('recommendedFurnitureIds', [1, 2])
            ->set('stage', ConversationStage::Checkout->value)
            ->set('readyToSubmit', true)
            ->call('newConversation')
            ->assertSet('messages', [
                [
                    'role' => 'assistant',
                    'content' => self::GREETING,
                ],
            ])
            ->assertSet('leadDraft', [])
            ->assertSet('recommendedFurnitureIds', [])
            ->assertSet('stage', ConversationStage::New->value)
            ->assertSet('readyToSubmit', false)
            ->assertSet('message', '');

        $this->assertSame(
            ConversationStage::New->value,
            session('ai_seller.conversation.stage'),
        );
    }
}
