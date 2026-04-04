<?php

namespace hexa_package_chatgpt\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_package_chatgpt\Services\ChatGptService;

/**
 * ChatGptServiceProvider — registers ChatGPT package services, routes, views.
 */
class ChatGptServiceProvider extends ServiceProvider
{
    /**
     * Centralized list of available OpenAI/ChatGPT models.
     *
     * @var array<int, string>
     */
    public static array $models = [
        'gpt-4o',
        'gpt-4-turbo',
        'gpt-4',
        'gpt-3.5-turbo',
    ];

    /**
     * Register services into the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/chatgpt.php', 'chatgpt');
        $this->app->singleton(ChatGptService::class);

        config(['chatgpt.available_models' => static::$models]);
    }

    /**
     * Bootstrap package resources.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/chatgpt.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'chatgpt');

        // Sidebar links — registered via PackageRegistryService with auto permission checks
        if (!config('hexa.app_controls_sidebar', false)) {
            $registry = app(\hexa_core\Services\PackageRegistryService::class);
            $registry->registerSidebarLink('chatgpt.index', 'ChatGPT', 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'Sandbox', 'chatgpt', 81);
        }
    
        // Documentation
        if (class_exists(\hexa_core\Services\DocumentationService::class)) {
            app(\hexa_core\Services\DocumentationService::class)->register('chatgpt', 'ChatGPT API', 'hexawebsystems/laravel-hexa-package-chatgpt', [
                ['title' => 'Overview', 'content' => '<p>OpenAI ChatGPT API integration. Provides API key management, connection testing, and raw API endpoint.</p>'],
            ]);
        }
}

}
