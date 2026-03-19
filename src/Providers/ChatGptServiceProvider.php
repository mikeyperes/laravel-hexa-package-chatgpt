<?php

namespace hexa_package_chatgpt\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_package_chatgpt\Services\ChatGptService;

class ChatGptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ->mergeConfigFrom(__DIR__ . '/../../config/chatgpt.php', 'chatgpt');
        $this->app->singleton(ChatGptService::class);
    }

    public function boot(): void {}
}
