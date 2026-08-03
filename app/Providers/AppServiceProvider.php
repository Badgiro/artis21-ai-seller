<?php

namespace App\Providers;

use App\Services\Llm\DeepSeekLlmClient;
use App\Services\Llm\LlmClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmClientInterface::class, DeepSeekLlmClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
