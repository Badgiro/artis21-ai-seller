<x-filament-panels::page>
    <div class="ai-seller-page">
        <header class="ai-seller-hero">
            <h1>AI-продавец Artis21</h1>
            <p>
                Отвечает за секунды 24/7, квалифицирует, рекомендует из каталога и записывает на замер.
                Справа — то, что агент извлёк из разговора.
            </p>
        </header>

        <div class="ai-seller-grid">
            <section class="ai-seller-panel ai-seller-chat">
                <header class="ai-seller-panel-header">
                <div class="flex items-center gap-3">
                    <div class="ai-seller-avatar">
                        A
                    </div>

                    <div>
                        <h2>
                            Artis21 · AI-продавец
                        </h2>
                        <p>
                            Мебель от производителя · 24/7
                        </p>
                    </div>
                </div>

                <x-filament::button
                    type="button"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:click="newConversation"
                    wire:loading.attr="disabled"
                    wire:target="newConversation,sendMessage,submitLead"
                >
                    Новый диалог
                </x-filament::button>
                </header>

                <div class="ai-seller-chat-content" aria-live="polite">
                    @foreach ($messages as $index => $chatMessage)
                        <div
                            wire:key="chat-message-{{ $index }}"
                            @class([
                                'ai-seller-message-row',
                                'is-user' => $chatMessage['role'] === 'user',
                            ])
                        >
                            <div
                                @class([
                                    'ai-seller-message',
                                    'is-user' => $chatMessage['role'] === 'user',
                                    'is-assistant' => $chatMessage['role'] !== 'user',
                                ])
                            >
                                {{ $chatMessage['content'] }}
                            </div>
                        </div>
                    @endforeach

                    @php($recommendedFurniture = $this->recommendedFurniture())

                    @if ($recommendedFurniture->isNotEmpty())
                        <section class="ai-seller-recommendations" wire:key="recommendations">
                            <div class="ai-seller-recommendations-heading">
                                <div>
                                    <h3>Подходящие варианты</h3>
                                    <p>Реальные позиции из каталога Artis21</p>
                                </div>
                                <span>{{ $recommendedFurniture->count() }}</span>
                            </div>

                            <div class="ai-seller-products">
                                @foreach ($recommendedFurniture as $furniture)
                                    <article class="ai-seller-product" wire:key="furniture-{{ $furniture->getKey() }}">
                                        <h4>{{ $furniture->name }}</h4>
                                        <dl>
                                            <div>
                                                <dt>Тип</dt>
                                                <dd>{{ $furniture->type->label() }}</dd>
                                            </div>
                                            <div>
                                                <dt>Цена</dt>
                                                <dd>{{ number_format((float) $furniture->price, 0, ',', ' ') }} ₽</dd>
                                            </div>
                                            <div>
                                                <dt>Размер</dt>
                                                <dd>{{ $furniture->room_size }}</dd>
                                            </div>
                                            <div>
                                                <dt>Стиль</dt>
                                                <dd>{{ $furniture->style->label() }}</dd>
                                            </div>
                                        </dl>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                <form wire:submit="sendMessage" class="ai-seller-composer">
                    <label for="ai-seller-message" class="sr-only">
                        Сообщение
                    </label>

                    <input
                        id="ai-seller-message"
                        type="text"
                        wire:model="message"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        autocomplete="off"
                        placeholder="Напишите сообщение..."
                        @disabled($stage === \App\Enums\ConversationStage::Submitted->value)
                        class="ai-seller-input"
                    >

                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-paper-airplane"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        :disabled="$stage === \App\Enums\ConversationStage::Submitted->value"
                    >
                        <span wire:loading.remove wire:target="sendMessage">Отправить</span>
                        <span wire:loading wire:target="sendMessage">Отправка…</span>
                    </x-filament::button>
                </form>
            </section>

            <aside class="ai-seller-panel ai-seller-lead">
                <header class="ai-seller-panel-header">
                    <div class="flex items-center gap-3">
                        <div class="ai-seller-lead-icon">
                        <x-filament::icon
                            icon="heroicon-o-identification"
                            class="size-6"
                        />
                        </div>

                        <div>
                            <h2>Карточка текущего лида</h2>
                            <p>Данные из диалога</p>
                        </div>
                    </div>
                </header>

                <div class="ai-seller-lead-content">
                    <span class="ai-seller-stage">
                        Этап: {{ $this->stageLabel() }}
                    </span>

                    <dl class="ai-seller-lead-fields">
                        @foreach ([
                            'furniture_type' => 'Тип мебели',
                            'budget' => 'Бюджет',
                            'room_size' => 'Помещение/размер',
                            'style' => 'Стиль',
                            'timeline' => 'Сроки',
                            'city' => 'Город',
                            'name' => 'Имя',
                            'phone' => 'Телефон',
                        ] as $key => $label)
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ $this->leadValue($key) }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($this->canSubmit())
                        <x-filament::button
                            type="button"
                            size="lg"
                            icon="heroicon-o-paper-airplane"
                            wire:click="submitLead"
                            wire:loading.attr="disabled"
                            wire:target="submitLead"
                            class="w-full"
                        >
                            <span wire:loading.remove wire:target="submitLead">Отправить заявку</span>
                            <span wire:loading wire:target="submitLead">Отправка…</span>
                        </x-filament::button>
                    @endif

                    <p class="ai-seller-lead-note">
                        Карточка обновляется автоматически. Данные сохраняются в заявку только после подтверждения.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
