<?php

namespace HexaPackageSmokeTests\LaravelHexaPackageChatgpt;

use hexa_package_chatgpt\Http\Controllers\ChatGptController;
use ReflectionMethod;
use Tests\TestCase;

class PackageSmokeTest extends TestCase
{
    public function test_package_manifest_config_and_provider_are_loadable(): void
    {
        $root = dirname(__DIR__, 2);
        $composerPath = $root . '/composer.json';
        $this->assertFileExists($composerPath);

        $composer = json_decode((string) file_get_contents($composerPath), true);
        $this->assertIsArray($composer);
        $this->assertSame('hexawebsystems/laravel-hexa-package-chatgpt', $composer['name'] ?? null);
        $this->assertArrayHasKey('autoload', $composer);

        $providers = $composer['extra']['laravel']['providers'] ?? [];
        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers, 'Package must declare at least one Laravel provider.');
        foreach ($providers as $provider) {
            $this->assertTrue(class_exists($provider), "Provider {$provider} is not autoloadable.");
        }

        $configFiles = glob($root . '/config/*.php') ?: [];
        $this->assertNotEmpty($configFiles, 'Package must ship a config file with a version.');

        $hasVersion = false;
        foreach ($configFiles as $configFile) {
            $config = require $configFile;
            $this->assertIsArray($config, basename($configFile) . ' must return an array.');
            if (isset($config['version'])) {
                $hasVersion = true;
                $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) $config['version']);
            }
        }

        $this->assertTrue($hasVersion, 'At least one package config file must expose a semantic version.');
    }

    public function test_api_key_mask_has_a_fixed_display_length(): void
    {
        $method = new ReflectionMethod(ChatGptController::class, 'maskApiKey');

        $this->assertSame('', $method->invoke(new ChatGptController(), null));
        $this->assertSame('************3456', $method->invoke(new ChatGptController(), 'sk-test-123456'));
        $this->assertSame(
            '************WXYZ',
            $method->invoke(new ChatGptController(), 'sk-proj-this-is-a-much-longer-secret-WXYZ')
        );
    }

    public function test_settings_view_exposes_provider_links_and_responsive_key_controls(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/settings/index.blade.php');

        $this->assertStringContainsString('https://platform.openai.com/api-keys', $view);
        $this->assertStringContainsString('https://platform.openai.com/settings/organization/billing/overview', $view);
        $this->assertStringContainsString('https://platform.openai.com/usage', $view);
        $this->assertStringContainsString('Test API Status', $view);
        $this->assertStringContainsString('type="password"', $view);
        $this->assertStringContainsString('x-text="maskedKey"', $view);
        $this->assertStringContainsString('min-w-0 flex-1 truncate', $view);
        $this->assertStringContainsString('rel="noopener noreferrer"', $view);
    }
}
