@extends('layouts.app')

@section('title', 'ChatGPT Raw — ' . config('hws.app_name'))
@section('header', 'ChatGPT — Raw Functions')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- API Key Status --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <span class="inline-block w-3 h-3 rounded-full {{ $hasApiKey ? 'bg-green-500' : 'bg-yellow-500' }}"></span>
            <span class="text-sm font-medium text-gray-700">API Key:</span>
            <span class="text-sm text-gray-500 font-mono">{{ $hasApiKey ? $maskedKey : 'Not configured' }}</span>
        </div>
        <span class="text-xs font-medium px-2 py-1 rounded-full {{ $hasApiKey ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
            {{ $hasApiKey ? 'Active' : 'Missing' }}
        </span>
    </div>

    {{-- Package Functions Index --}}
    <div class="bg-gray-900 rounded-xl p-6 text-sm font-mono">
        <h2 class="text-white font-semibold mb-3">ChatGPT Functions</h2>
        <table class="w-full text-left">
            <thead>
                <tr class="text-gray-400 border-b border-gray-700">
                    <th class="py-1.5 px-2">Function</th>
                    <th class="py-1.5 px-2">Method</th>
                    <th class="py-1.5 px-2">Route</th>
                    <th class="py-1.5 px-2">Status</th>
                </tr>
            </thead>
            <tbody class="text-gray-300">
                <tr class="border-b border-gray-800">
                    <td class="py-1.5 px-2">Test API key validity</td>
                    <td class="py-1.5 px-2 text-blue-400">testApiKey()</td>
                    <td class="py-1.5 px-2 text-green-400">POST /settings/chatgpt/test</td>
                    <td class="py-1.5 px-2 text-green-400">LIVE</td>
                </tr>
                <tr class="border-b border-gray-800">
                    <td class="py-1.5 px-2">Send chat completion</td>
                    <td class="py-1.5 px-2 text-blue-400">chat(systemPrompt, userMessage, model, temperature, maxTokens)</td>
                    <td class="py-1.5 px-2 text-green-400">POST /chatgpt/chat</td>
                    <td class="py-1.5 px-2 text-green-400">LIVE</td>
                </tr>
                <tr class="border-b border-gray-800">
                    <td class="py-1.5 px-2">Spin/rewrite article content</td>
                    <td class="py-1.5 px-2 text-blue-400">spinArticle(content, instruction, articleType, tone)</td>
                    <td class="py-1.5 px-2 text-gray-500">Uses chat() internally</td>
                    <td class="py-1.5 px-2 text-green-400">LIVE</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chat Test --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Chat with ChatGPT</h2>

        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">System Prompt</label>
                <textarea id="chatgpt-system" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="You are a helpful assistant...">You are a helpful assistant.</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User Message</label>
                <textarea id="chatgpt-message" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Enter your message..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                    <select id="chatgpt-model" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="gpt-4o">gpt-4o</option>
                        <option value="gpt-4o-mini">gpt-4o-mini</option>
                        <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Temperature: <span id="chatgpt-temp-value">0.7</span></label>
                    <input type="range" id="chatgpt-temperature" min="0" max="2" step="0.1" value="0.7" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer mt-2">
                </div>
            </div>
            <button id="btn-chatgpt-chat" class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
                Send
            </button>
        </div>

        <div id="chatgpt-chat-result" class="mt-4"></div>
    </div>
</div>

@push('scripts')
<script>
// Temperature slider display
document.getElementById('chatgpt-temperature').addEventListener('input', function() {
    document.getElementById('chatgpt-temp-value').textContent = this.value;
});

document.getElementById('btn-chatgpt-chat').addEventListener('click', function() {
    var btn = this;
    var originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-4 w-4 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sending...';

    var resultDiv = document.getElementById('chatgpt-chat-result');

    fetch('{{ route("chatgpt.chat") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            system_prompt: document.getElementById('chatgpt-system').value,
            user_message: document.getElementById('chatgpt-message').value,
            model: document.getElementById('chatgpt-model').value,
            temperature: parseFloat(document.getElementById('chatgpt-temperature').value)
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        var html = '';
        if (data.success && data.data) {
            html += '<div class="p-4 rounded-lg bg-green-50 border border-green-200 mb-3">';
            html += '<div class="flex items-center justify-between mb-2">';
            html += '<span class="text-sm font-medium text-green-800">Response from ' + escapeHtml(data.data.model) + '</span>';
            html += '</div>';
            html += '<div class="text-sm text-gray-800 break-words whitespace-pre-wrap">' + escapeHtml(data.data.content) + '</div>';
            // Token usage
            if (data.data.usage) {
                html += '<div class="mt-3 pt-3 border-t border-green-200 text-xs text-gray-500">';
                html += 'Prompt tokens: <span class="font-mono">' + (data.data.usage.prompt_tokens || 0) + '</span>';
                html += ' &middot; Completion tokens: <span class="font-mono">' + (data.data.usage.completion_tokens || 0) + '</span>';
                html += ' &middot; Total: <span class="font-mono">' + (data.data.usage.total_tokens || 0) + '</span>';
                html += '</div>';
            }
            html += '</div>';
        } else {
            html = '<div class="p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800">' + escapeHtml(data.message || 'Error') + '</div>';
        }
        resultDiv.innerHTML = html;
    })
    .catch(function(err) {
        resultDiv.innerHTML = '<div class="p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800">Request failed: ' + escapeHtml(err.message) + '</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = originalText;
    });
});

function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}
</script>
@endpush
@endsection
