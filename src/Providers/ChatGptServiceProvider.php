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

        // Sidebar links — package-owned and auto-wired into the core registry.
        $registry = app(\hexa_core\Services\PackageRegistryService::class);
        // HWS-SIDEBAR-MENU-3L-BEGIN
        $registry->registerDomainGroup('AI', 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 30);
        $registry->registerSectionGroup('ChatGPT', 'AI', '', 30);
        // HWS-SIDEBAR-MENU-3L-END

        $registry->registerSidebarLink('settings.chatgpt', 'Settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.11 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'ChatGPT', 'chatgpt', 80);
        $registry->registerSidebarLink('chatgpt.index', 'Sandbox', 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'ChatGPT', 'chatgpt', 81);
        if (method_exists($registry, 'registerPackage')) {
            $registry->registerPackage('chatgpt', 'hexawebsystems/laravel-hexa-package-chatgpt', [
            'title' => 'ChatGPT API',
            'color' => 'green',
            'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
            'description' => 'Legacy OpenAI ChatGPT sandbox package for raw prompt and response testing.',
            'settingsRoute' => 'settings.chatgpt',
            'settingsShellClass' => 'max-w-4xl',
            'docsSlug' => 'chatgpt',
            'instructions' => [
                'Configure the OpenAI API key in the shared OpenAI settings page.',
                'Use this package for raw ChatGPT package tests only; provider settings stay in OpenAI.',
            ],
            'apiLinks' => [
                ['label' => 'OpenAI API Keys', 'url' => 'https://platform.openai.com/api-keys'],
                ['label' => 'OpenAI Docs', 'url' => 'https://platform.openai.com/docs'],
            ],
            ]);
        }
    
        // Documentation
        if (class_exists(\hexa_core\Services\DocumentationService::class)) {
            app(\hexa_core\Services\DocumentationService::class)->register('chatgpt', 'ChatGPT API', 'hexawebsystems/laravel-hexa-package-chatgpt', [
                ['title' => 'Overview', 'content' => '<p>OpenAI ChatGPT API integration. Provides API key management, connection testing, and raw API endpoint.</p>'],
            ]);
        }
}

/** Brand prefix for dropdown labels. */
    public const BRAND = 'OpenAI';

    /** Get 'Company — Model' label for a model id. */
    public static function getDropdownLabel(string $modelId): string
    {
        foreach ((array) config('chatgpt.models', []) as $m) {
            if (($m['id'] ?? '') === $modelId) {
                return self::BRAND . ' — ' . ($m['name'] ?? $modelId);
            }
        }
        return self::BRAND . ' — ' . $modelId;
    }

    /** Get 'Company — Model — $in/$out per 1M' label for a model id. */
    public static function getDropdownLabelWithPrice(string $modelId): string
    {
        foreach ((array) config('chatgpt.models', []) as $m) {
            if (($m['id'] ?? '') === $modelId) {
                $name = $m['name'] ?? $modelId;
                if (isset($m['price_input'], $m['price_output'])) {
                    $in = rtrim(rtrim(number_format($m['price_input'], 2), '0'), '.');
                    $out = rtrim(rtrim(number_format($m['price_output'], 2), '0'), '.');
                    return self::BRAND . ' — ' . $name . ' — $' . $in . '/$' . $out . ' per 1M';
                }
                return self::BRAND . ' — ' . $name;
            }
        }
        return self::BRAND . ' — ' . $modelId;
    }
}
