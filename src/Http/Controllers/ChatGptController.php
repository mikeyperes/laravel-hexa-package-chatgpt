<?php

namespace hexa_package_chatgpt\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use hexa_package_chatgpt\Services\ChatGptService;
use hexa_core\Models\Setting;

/**
 * ChatGptController — handles raw view and API endpoints for the ChatGPT package.
 */
class ChatGptController extends Controller
{
    /**
     * Show the settings page.
     *
     * @return \Illuminate\View\View
     */
    public function settings()
    {
        $apiKey = Setting::getValue('chatgpt_api_key', '');
        return view('chatgpt::settings.index', [
            'hasApiKey' => !empty($apiKey),
            'maskedKey' => $this->maskApiKey($apiKey),
            'modelSync' => app(ChatGptService::class)->getModelSyncState(),
        ]);
    }

    /**
     * Save the API key.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveKey(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'required|string|min:10|max:512',
        ]);
        $apiKey = trim((string) $validated['api_key']);

        Setting::setValue('chatgpt_api_key', $apiKey);

        return response()->json([
            'success' => true,
            'message' => 'API key saved. Run Test API Status to verify provider access.',
            'masked_key' => $this->maskApiKey($apiKey),
        ]);
    }

    /**
     * Test the API key.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testKey()
    {
        $result = app(ChatGptService::class)->testApiKey();
        return response()->json($result);
    }

    public function syncModels(Request $request)
    {
        $result = app(ChatGptService::class)->syncAvailableModels($request->boolean('purge_cache'));

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['state'],
        ], $result['success'] ? 200 : 422);
    }

    public function purgeAndSyncModels()
    {
        $result = app(ChatGptService::class)->syncAvailableModels(true);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['state'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Show the raw development/test page.
     *
     * @return \Illuminate\View\View
     */
    public function raw()
    {
        $apiKey = Setting::getValue('chatgpt_api_key', '');
        $maskedKey = $this->maskApiKey($apiKey);

        return view('chatgpt::raw.index', [
            'hasApiKey' => !empty($apiKey),
            'maskedKey' => $maskedKey,
        ]);
    }

    /**
     * Send a chat message to OpenAI/ChatGPT.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        $request->validate([
            'system_prompt' => 'required|string',
            'user_message' => 'required|string',
            'model' => 'required|string',
        ]);

        $service = app(ChatGptService::class);
        $result = $service->chat(
            $request->input('system_prompt'),
            $request->input('user_message'),
            $request->input('model', 'gpt-4o'),
            (float) $request->input('temperature', 0.7),
            (int) $request->input('max_tokens', 4096)
        );

        return response()->json($result);
    }

    private function maskApiKey(?string $apiKey): string
    {
        return filled($apiKey) ? '************' . substr((string) $apiKey, -4) : '';
    }
}
