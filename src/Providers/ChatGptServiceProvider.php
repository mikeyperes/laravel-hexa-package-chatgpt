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
     * @return array<int, array{id: string, name: string}>
     */
    public static function getModels(): array
    {
        if (app()->bound(ChatGptService::class)) {
            try {
                return app(ChatGptService::class)->getAvailableModels();
            } catch (\Throwable) {
            }
        }

        return array_values(array_map(
            static fn (array $model): array => [
                'id' => (string) ($model['id'] ?? ''),
                'name' => (string) ($model['name'] ?? ($model['id'] ?? '')),
            ],
            (array) config('chatgpt.models', [])
        ));
    }

    /**
     * Register services into the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/chatgpt.php', 'chatgpt');
        $this->app->singleton(ChatGptService::class);

        config([
            'chatgpt.available_models' => array_values(array_map(
                static fn (array $model): string => (string) $model['id'],
                static::getModels()
            )),
        ]);
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
        if (method_exists($registry, 'registerPackage')) {
            $registry->registerPackage('chatgpt', 'hexawebsystems/laravel-hexa-package-chatgpt', [
            'title' => 'ChatGPT API',
            'color' => 'green',
            'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
            'description' => 'Legacy OpenAI ChatGPT package for provider-backed raw prompt and response testing.',
            'settingsRoute' => 'settings.chatgpt',
            'settingsShellClass' => 'max-w-4xl',
            'docsSlug' => 'chatgpt',
            'instructions' => [
                'Create or rotate the OpenAI API key from the OpenAI API Keys page.',
                'Add funds or confirm payment details in OpenAI Billing & Credits.',
                'Save the key, then run Test API Status to verify provider access.',
                'Review account consumption on the OpenAI Usage page.',
            ],
            'apiLinks' => [
                ['label' => 'OpenAI API Keys', 'url' => 'https://platform.openai.com/api-keys'],
                ['label' => 'Billing & Credits', 'url' => 'https://platform.openai.com/settings/organization/billing/overview'],
                ['label' => 'Usage', 'url' => 'https://platform.openai.com/usage'],
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
