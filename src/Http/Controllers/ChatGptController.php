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
            'maskedKey' => $apiKey ? str_repeat('•', max(0, strlen($apiKey) - 4)) . substr($apiKey, -4) : '',
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
        $request->validate(['api_key' => 'required|string|min:10']);
        Setting::setValue('chatgpt_api_key', $request->input('api_key'));
        return response()->json(['success' => true, 'message' => 'API key saved.']);
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

    /**
     * Show the raw development/test page.
     *
     * @return \Illuminate\View\View
     */
    public function raw()
    {
        $apiKey = Setting::getValue('chatgpt_api_key', '');
        $maskedKey = $apiKey ? str_repeat('*', max(0, strlen($apiKey) - 4)) . substr($apiKey, -4) : '';

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
}
