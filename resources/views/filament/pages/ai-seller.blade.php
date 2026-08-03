<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 lg:col-span-2">
            <header class="flex flex-col gap-4 border-b border-gray-200 px-6 py-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white">
                        <x-filament::icon
                            icon="heroicon-o-sparkles"
                            class="size-6"
                        />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                            Artis21 · AI-продавец
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
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
                    wire:target="newConversation,sendMessage"
                >
                    Новый диалог
                </x-filament::button>
            </header>

            <div
                class="flex min-h-96 flex-col gap-4 overflow-y-auto bg-gray-50/60 p-6 dark:bg-gray-950/30"
                aria-live="polite"
            >
                @foreach ($messages as $index => $chatMessage)
                    <div
                        wire:key="chat-message-{{ $index }}"
                        @class([
                            'flex',
                            'justify-end' => $chatMessage['role'] === 'user',
                            'justify-start' => $chatMessage['role'] !== 'user',
                        ])
                    >
                        <div
                            @class([
                                'max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm sm:max-w-[75%]',
                                'rounded-br-md bg-primary-600 text-white' => $chatMessage['role'] === 'user',
                                'rounded-bl-md bg-white text-gray-950 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-white dark:ring-white/10' => $chatMessage['role'] !== 'user',
                            ])
                        >
                            {{ $chatMessage['content'] }}
                        </div>
                    </div>
                @endforeach
            </div>

            <form
                wire:submit="sendMessage"
                class="border-t border-gray-200 p-4 dark:border-white/10"
            >
                <div class="flex flex-col gap-3 sm:flex-row">
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
                        class="block min-w-0 flex-1 rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 disabled:opacity-70 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500"
                    >

                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-paper-airplane"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                    >
                        <span wire:loading.remove wire:target="sendMessage">Отправить</span>
                        <span wire:loading wire:target="sendMessage">Отправка…</span>
                    </x-filament::button>
                </div>
            </form>
        </section>

        <aside class="self-start overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <header class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white">
                        <x-filament::icon
                            icon="heroicon-o-identification"
                            class="size-6"
                        />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                            Карточка текущего лида
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Данные из диалога
                        </p>
                    </div>
                </div>
            </header>

            <div class="space-y-5 p-6">
                <div>
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-400/10 dark:text-primary-400">
                        Этап: {{ $this->stageLabel() }}
                    </span>
                </div>

                <dl class="divide-y divide-gray-200 rounded-xl bg-gray-50 px-4 dark:divide-white/10 dark:bg-white/5">
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
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $label }}
                            </dt>
                            <dd class="text-right text-sm font-medium text-gray-950 dark:text-white">
                                {{ $this->leadValue($key) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                    На этом этапе карточка доступна только для просмотра. AI-интеграция будет подключена позже.
                </p>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
