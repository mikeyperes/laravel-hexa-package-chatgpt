<?php

namespace HexaPackageSmokeTests\LaravelHexaPackageChatgpt;

use hexa_core\AI\Contracts\ArticleSearchOptimizer;
use hexa_package_chatgpt\Services\ChatGptService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArticleSearchOptimizerContractTest extends TestCase
{
    public function test_bound_optimizer_receives_provider_search_context(): void
    {
        Http::preventStrayRequests();
        $optimizer = new RecordingOpenAiSearchOptimizer;
        $this->app->instance(ArticleSearchOptimizer::class, $optimizer);

        $result = (new StubbedChatGptService)->searchArticlesOptimized('contract topic', 3, 'gpt-4o-mini');

        $this->assertTrue($result['success']);
        $this->assertSame([
            'topic' => 'contract topic',
            'count' => 3,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'options' => [
                'backend_label' => 'OpenAI Optimized Search',
                'query_plan' => StubbedChatGptService::QUERY_PLAN,
                'seed_articles' => [StubbedChatGptService::SEED_ARTICLE],
            ],
        ], $optimizer->calls[0]);
        $this->assertSame(['input_tokens' => 5, 'output_tokens' => 7, 'total_tokens' => 12], $result['data']['usage']);
        $this->assertSame('gpt-4o-mini', $result['data']['model']);
    }

    public function test_unbound_optimizer_preserves_native_search_fallback(): void
    {
        Http::preventStrayRequests();
        unset($this->app[ArticleSearchOptimizer::class]);

        $result = (new StubbedChatGptService)->searchArticlesOptimized('fallback topic', 3, 'gpt-4o-mini');

        $this->assertFalse($this->app->bound(ArticleSearchOptimizer::class));
        $this->assertTrue($result['success']);
        $this->assertSame([StubbedChatGptService::SEED_ARTICLE], $result['data']['articles']);
        $this->assertSame(StubbedChatGptService::QUERY_PLAN, $result['data']['query_plan']);
        $this->assertSame('openai_model_search', $result['data']['search_backend']);
        $this->assertSame('OpenAI Model Search', $result['data']['search_backend_label']);
        $this->assertSame(['input_tokens' => 5, 'output_tokens' => 7, 'total_tokens' => 12], $result['data']['usage']);
    }

    public function test_failed_optimizer_preserves_native_search_fallback(): void
    {
        Http::preventStrayRequests();
        $optimizer = new RecordingOpenAiSearchOptimizer;
        $optimizer->successful = false;
        $this->app->instance(ArticleSearchOptimizer::class, $optimizer);

        $result = (new StubbedChatGptService)->searchArticlesOptimized('fallback topic', 3, 'gpt-4o-mini');

        $this->assertTrue($result['success']);
        $this->assertSame('openai_model_search', $result['data']['search_backend']);
        $this->assertCount(1, $optimizer->calls);
    }

    public function test_provider_source_has_no_publish_application_dependency(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Services/ChatGptService.php');

        $this->assertStringContainsString('ArticleSearchOptimizer::class', $source);
        $this->assertStringNotContainsString('hexa_app'.'_publish', $source);
    }
}

final class RecordingOpenAiSearchOptimizer implements ArticleSearchOptimizer
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public bool $successful = true;

    public function search(string $topic, int $count, string $provider, string $model, array $options = []): array
    {
        $this->calls[] = compact('topic', 'count', 'provider', 'model', 'options');

        return $this->successful
            ? [
                'success' => true,
                'message' => 'Optimized search complete.',
                'data' => ['articles' => [], 'search_backend' => 'contract_optimizer'],
            ]
            : ['success' => false, 'message' => 'Optimizer unavailable.', 'data' => null];
    }
}

final class StubbedChatGptService extends ChatGptService
{
    public const QUERY_PLAN = [
        'queries' => ['contract topic journalism'],
        'required_terms' => ['contract'],
        'avoid_terms' => ['sponsored'],
        'angle' => 'contract coverage',
    ];

    public const SEED_ARTICLE = [
        'url' => 'https://example.com/openai-seed',
        'title' => 'OpenAI Seed',
        'description' => 'A verified seed.',
    ];

    public function chat(
        string $systemPrompt,
        string $userMessage,
        string $model = 'gpt-4o',
        float $temperature = 0.7,
        int $maxTokens = 4096,
    ): array {
        $isPlan = str_starts_with($userMessage, 'Topic:');

        return [
            'success' => true,
            'message' => 'Stubbed response.',
            'data' => [
                'content' => json_encode($isPlan ? self::QUERY_PLAN : [self::SEED_ARTICLE], JSON_THROW_ON_ERROR),
                'model' => $model,
                'usage' => $isPlan
                    ? ['input_tokens' => 1, 'output_tokens' => 2, 'total_tokens' => 3]
                    : ['input_tokens' => 4, 'output_tokens' => 5, 'total_tokens' => 9],
            ],
        ];
    }
}
