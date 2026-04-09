<?php

namespace hexa_package_chatgpt\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use hexa_core\Models\Setting;

class ChatGptService
{
    /**
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        return Setting::getValue('chatgpt_api_key');
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
}
