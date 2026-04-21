<?php

namespace hexa_package_chatgpt\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use hexa_core\Models\Setting;

class ChatGptService
{
    private const MODEL_CACHE_KEY = 'chatgpt:available-models';
    private const MODEL_STATE_SETTING_KEY = 'chatgpt_available_models_state';

    /**
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        return Setting::getValue('chatgpt_api_key');
    }

    public function hasApiKey(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        return $this->getModelSyncState($forceRefresh)['models'];
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, count: int, source: string, source_label: string, last_synced_at: string|null, last_synced_human: string, message: string}
     */
    public function getModelSyncState(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            return $this->syncAvailableModels(true)['state'];
        }

        $cached = Cache::get(self::MODEL_CACHE_KEY);
        if (is_array($cached) && isset($cached['models'])) {
            return $cached;
        }

        $stored = $this->storedModelSyncState();
        if ($stored !== null) {
            Cache::forever(self::MODEL_CACHE_KEY, $stored);

            return $stored;
        }

        $fallback = $this->fallbackState('Never synced. Showing packaged defaults.');
        Cache::forever(self::MODEL_CACHE_KEY, $fallback);

        return $fallback;
    }

    /**
     * @return array{success: bool, message: string, state: array{models: array<int, array{id: string, name: string}>, count: int, source: string, source_label: string, last_synced_at: string|null, last_synced_human: string, message: string}}
     */
    public function syncAvailableModels(bool $purgeCache = false): array
    {
        if ($purgeCache) {
            $this->purgeModelCache();
        }

        $stored = $this->storedModelSyncState();

        if (!$this->hasApiKey()) {
            $state = $this->fallbackState('No OpenAI API key configured. Showing packaged defaults.', $stored);
            Cache::forever(self::MODEL_CACHE_KEY, $state);

            return [
                'success' => false,
                'message' => 'No OpenAI API key configured. Showing packaged defaults.',
                'state' => $state,
            ];
        }

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->getApiKey()])
                ->timeout(20)
                ->get('https://api.openai.com/v1/models');

            if (!$response->successful()) {
                $message = $response->json('error.message')
                    ?: ('OpenAI returned HTTP ' . $response->status() . '.');

                throw new \RuntimeException($message);
            }

            $models = $this->normalizeRemoteModels((array) $response->json('data', []));
            if ($models === []) {
                throw new \RuntimeException('OpenAI returned no usable text-generation models.');
            }

            $state = $this->buildState($models, 'remote_api', 'Models synced with OpenAI.');
            $this->persistModelSyncState($state);
            Cache::forever(self::MODEL_CACHE_KEY, $state);

            return [
                'success' => true,
                'message' => 'Models synced with OpenAI.',
                'state' => $state,
            ];
        } catch (\Throwable $e) {
            $state = $stored ?? $this->fallbackState('Sync failed. Showing packaged defaults.');
            Cache::forever(self::MODEL_CACHE_KEY, $state);

            return [
                'success' => false,
                'message' => 'Model sync failed: ' . $e->getMessage(),
                'state' => $state,
            ];
        }
    }

    public function purgeModelCache(): void
    {
        Cache::forget(self::MODEL_CACHE_KEY);
    }

    /**
     * Test the API key.
     *
     * @param string|null $apiKey Override key to test.
     * @return array{success: bool, message: string}
     */
    public function testApiKey(?string $apiKey = null): array
    {
        $key = $apiKey ?? $this->getApiKey();
        if (!$key) {
            return ['success' => false, 'message' => 'No ChatGPT/OpenAI API key configured.'];
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$key}"])
                ->timeout(10)
                ->get('https://api.openai.com/v1/models');

            if ($response->successful()) {
                return ['success' => true, 'message' => 'OpenAI API key is valid.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Invalid API key.'];
            }
            return ['success' => false, 'message' => "OpenAI returned HTTP {$response->status()}."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Send a chat completion request.
     *
     * @param string $systemPrompt System-level instructions.
     * @param string $userMessage The user/article content.
     * @param string $model OpenAI model name.
     * @param float $temperature 0-2, lower = more deterministic.
     * @param int $maxTokens Max response tokens.
     * @return array{success: bool, message: string, data: array|null}
     */
    public function chat(string $systemPrompt, string $userMessage, string $model = 'gpt-4o', float $temperature = 0.7, int $maxTokens = 4096): array
    {
        $key = $this->getApiKey();
        if (!$key) {
            return ['success' => false, 'message' => 'No ChatGPT/OpenAI API key configured.', 'data' => null];
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$key}"])
                ->timeout(120)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $usage = $data['usage'] ?? [];

                return [
                    'success' => true,
                    'message' => 'Response received.',
                    'data' => [
                        'content' => $content,
                        'model' => $data['model'] ?? $model,
                        'usage' => [
                            'input_tokens' => $usage['prompt_tokens'] ?? 0,
                            'output_tokens' => $usage['completion_tokens'] ?? 0,
                        ],
                    ],
                ];
            }

            $error = $response->json();
            $errorMsg = $error['error']['message'] ?? "HTTP {$response->status()}";
            return ['success' => false, 'message' => "OpenAI error: {$errorMsg}", 'data' => null];
        } catch (\Exception $e) {
            Log::error('ChatGptService::chat error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Spin/rewrite article content.
     *
     * @param string $articleContent The original article content.
     * @param string $instruction User instruction (e.g. "optimize for SEO", "adjust tone").
     * @param string|null $articleType Article type for context.
     * @param string|null $tone Desired tone.
     * @return array{success: bool, message: string, data: array|null}
     */
    public function spinArticle(string $articleContent, string $instruction = '', ?string $articleType = null, ?string $tone = null): array
    {
        $systemPrompt = "You are a professional content editor and writer. Rewrite the provided article content.";

        if ($articleType) {
            $systemPrompt .= " The article type is: {$articleType}.";
        }
        if ($tone) {
            $systemPrompt .= " Write in a {$tone} tone.";
        }

        $systemPrompt .= " Output ONLY the rewritten article content in HTML format. Do not include explanations or commentary.";

        $userMessage = '';
        if ($instruction) {
            $userMessage .= "Instructions: {$instruction}\n\n";
        }
        $userMessage .= "Article to rewrite:\n\n{$articleContent}";

        return $this->chat($systemPrompt, $userMessage);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function fallbackModels(): array
    {
        return array_values(array_map(
            static fn (array $model): array => [
                'id' => (string) ($model['id'] ?? ''),
                'name' => (string) ($model['name'] ?? ($model['id'] ?? '')),
            ],
            array_filter((array) config('chatgpt.models', []), static fn (array $model): bool => !empty($model['id']))
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $remoteModels
     * @return array<int, array{id: string, name: string}>
     */
    private function normalizeRemoteModels(array $remoteModels): array
    {
        $knownNames = collect($this->fallbackModels())->pluck('name', 'id')->all();
        $preferredOrder = array_flip(array_column($this->fallbackModels(), 'id'));

        return collect($remoteModels)
            ->map(function ($model) use ($knownNames) {
                $id = trim((string) ($model['id'] ?? ''));
                if (!$this->isSupportedTextModel($id)) {
                    return null;
                }

                $name = trim((string) ($knownNames[$id] ?? ''));
                if ($name === '') {
                    $name = $this->humanizeModelId($id);
                }

                return ['id' => $id, 'name' => $name];
            })
            ->filter()
            ->unique('id')
            ->sortBy(fn (array $model): string => sprintf('%08d-%s', $preferredOrder[$model['id']] ?? 99999999, $model['name']))
            ->values()
            ->all();
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, count: int, source: string, source_label: string, last_synced_at: string|null, last_synced_human: string, message: string}
     */
    private function buildState(array $models, string $source, string $message, ?string $lastSyncedAt = null): array
    {
        $lastSyncedAt ??= now()->toIso8601String();

        return [
            'models' => array_values($models),
            'count' => count($models),
            'source' => $source,
            'source_label' => $source === 'remote_api' ? 'OpenAI API' : 'Packaged Defaults',
            'last_synced_at' => $lastSyncedAt,
            'last_synced_human' => $lastSyncedAt ? Carbon::parse($lastSyncedAt)->diffForHumans() : 'never',
            'message' => $message,
        ];
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, count: int, source: string, source_label: string, last_synced_at: string|null, last_synced_human: string, message: string}
     */
    private function fallbackState(string $message, ?array $stored = null): array
    {
        return $this->buildState(
            $this->fallbackModels(),
            'config_fallback',
            $message,
            $stored['last_synced_at'] ?? null
        );
    }

    private function persistModelSyncState(array $state): void
    {
        Setting::setValue(self::MODEL_STATE_SETTING_KEY, json_encode($state, JSON_UNESCAPED_SLASHES), 'chatgpt');
    }

    private function storedModelSyncState(): ?array
    {
        $raw = Setting::getValue(self::MODEL_STATE_SETTING_KEY);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['models']) || !is_array($decoded['models'])) {
            return null;
        }

        return $this->buildState(
            $decoded['models'],
            (string) ($decoded['source'] ?? 'remote_api'),
            (string) ($decoded['message'] ?? 'Using last successful sync.'),
            !empty($decoded['last_synced_at']) ? (string) $decoded['last_synced_at'] : null
        );
    }

    private function isSupportedTextModel(string $modelId): bool
    {
        if ($modelId === '') {
            return false;
        }

        if (!preg_match('/^(gpt-|o[1-9]|o\\d)/', $modelId)) {
            return false;
        }

        return !preg_match('/(audio|realtime|transcribe|tts|moderation|embedding|whisper|image|vision|dall-e|search|deep-research|chatgpt-|computer-use)/i', $modelId);
    }

    private function humanizeModelId(string $modelId): string
    {
        $label = strtoupper(str_replace(['-', '_'], ' ', $modelId));
        $label = preg_replace('/\s+/', ' ', $label) ?: $modelId;

        return $label;
    }
}
