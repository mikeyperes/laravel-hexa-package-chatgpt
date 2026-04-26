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
     * @return array{success: bool, message: string, data: array|null}
     */
    public function searchArticlesOptimized(string $topic, int $count = 4, ?string $model = null): array
    {
        $count = max(2, min(10, $count));
        $model ??= 'gpt-4o-mini';

        $planResult = $this->buildOptimizedNewsQueryPlan($topic, $model);
        $usage = $this->aggregateUsage([
            (array) data_get($planResult, 'data.usage', []),
        ]);

        if (class_exists(\hexa_app_publish\Discovery\Sources\Services\OptimizedNewsSearchService::class)) {
            $optimized = app(\hexa_app_publish\Discovery\Sources\Services\OptimizedNewsSearchService::class)->search($topic, $count, 'openai', $model, [
                'backend_label' => 'OpenAI Optimized Search',
                'query_plan' => (array) data_get($planResult, 'data.query_plan', []),
            ]);

            if (is_array($optimized['data'] ?? null)) {
                $optimized['data']['usage'] = $usage;
                $optimized['data']['model'] = $optimized['data']['model'] ?? $model;
            }

            return $optimized;
        }

        $legacy = $this->searchArticlesViaChat($topic, $count, $model);
        if (is_array($legacy['data'] ?? null)) {
            $legacy['data']['usage'] = $this->aggregateUsage([
                (array) data_get($legacy, 'data.usage', []),
                $usage,
            ]);
            $legacy['data']['model'] = $legacy['data']['model'] ?? $model;
            $legacy['data']['query_plan'] = (array) data_get($planResult, 'data.query_plan', []);
            $legacy['data']['search_backend'] = $legacy['data']['search_backend'] ?? 'openai_chat_search';
            $legacy['data']['search_backend_label'] = $legacy['data']['search_backend_label'] ?? 'OpenAI Chat Search';
        }

        if ($legacy['success']) {
            return $legacy;
        }

        return [
            'success' => false,
            'message' => $legacy['message'] ?? ($planResult['message'] ?? 'OpenAI optimized search failed.'),
            'data' => [
                'model' => $model,
                'usage' => $usage,
                'query_plan' => (array) data_get($planResult, 'data.query_plan', []),
                'search_backend' => 'openai_optimized_search',
                'search_backend_label' => 'OpenAI Optimized Search',
            ],
        ];
    }

    /**
     * @return array{success: bool, message: string, data: array|null}
     */
    private function buildOptimizedNewsQueryPlan(string $topic, string $model): array
    {
        $systemPrompt = 'You are a news-search strategist. Build precise search-engine queries for finding real recent journalism.';
        $userMessage = "Topic: {$topic}
"
            . "Return ONLY a JSON object with keys: queries, required_terms, avoid_terms, angle. "
            . "queries must be an array of 3 to 5 concise search queries aimed at finding real recent news articles. "
            . "required_terms must be an array of 1 to 4 terms that every good article should match. "
            . "avoid_terms must be an array of low-value terms to avoid, like press release, sponsored, roundup, or directory when relevant. "
            . "angle must be a short phrase describing the best concrete news angle.
"
            . "Do not include markdown or explanations.";

        $result = $this->chat($systemPrompt, $userMessage, $model, 0.2, 800);
        if (!$result['success']) {
            return $result;
        }

        $plan = $this->parseJsonObject((string) data_get($result, 'data.content', ''));
        if (!$plan) {
            return [
                'success' => false,
                'message' => 'OpenAI did not return a usable search plan.',
                'data' => $result['data'],
            ];
        }

        $result['data']['query_plan'] = $plan;

        return $result;
    }

    /**
     * @return array{success: bool, message: string, data: array|null}
     */
    private function searchArticlesViaChat(string $topic, int $count, string $model): array
    {
        $systemPrompt = 'You are a research assistant with web access. Find real, recent news articles. Output ONLY valid JSON.';
        $userMessage = "Search the web for {$count} recent news articles about: {$topic}. "
            . "Return only LIVE, canonical article pages from reputable publishers. "
            . "Do NOT guess URL slugs. Do NOT return homepages, search pages, tag pages, category pages, topic pages, author pages, archive pages, AMP pages, cached pages, redirect links, or Google intermediary links. "
            . "For each article return the exact canonical URL, the article title, and a brief description under 20 words. "
            . "Return ONLY a JSON array of objects with keys: url, title, description.";

        $result = $this->chat($systemPrompt, $userMessage, $model, 0.3, 2048);
        if (!$result['success']) {
            return $result;
        }

        $articles = $this->parseArticleArray((string) data_get($result, 'data.content', ''));
        if ($articles === []) {
            return [
                'success' => false,
                'message' => 'OpenAI returned no usable article JSON.',
                'data' => $result['data'],
            ];
        }

        $result['data']['articles'] = $articles;

        return $result;
    }

    /**
     * @return array<int, array{url: string, title: string, description: string}>
     */
    private function parseArticleArray(string $text): array
    {
        $json = $this->extractJsonSegment($text, '[', ']');
        if ($json === null) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $articles = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            if ($url === '' || $title === '') {
                continue;
            }

            $articles[] = [
                'url' => $url,
                'title' => $title,
                'description' => trim((string) ($item['description'] ?? '')),
            ];
        }

        return $articles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonObject(string $text): ?array
    {
        $json = $this->extractJsonSegment($text, '{', '}');
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractJsonSegment(string $text, string $openChar, string $closeChar): ?string
    {
        $start = strpos($text, $openChar);
        $end = strrpos($text, $closeChar);

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }

    /**
     * @param array<int, array<string, int|float>> $usages
     * @return array{input_tokens: int, output_tokens: int, total_tokens: int}
     */
    private function aggregateUsage(array $usages): array
    {
        $input = 0;
        $output = 0;
        $total = 0;

        foreach ($usages as $usage) {
            $input += (int) ($usage['input_tokens'] ?? 0);
            $output += (int) ($usage['output_tokens'] ?? 0);
            $total += (int) ($usage['total_tokens'] ?? (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0)));
        }

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ];
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
