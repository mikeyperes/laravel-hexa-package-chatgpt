@extends('layouts.app')
@section('title', 'ChatGPT Settings')
@section('content')
<div class="max-w-4xl mx-auto" x-data="{
    hasToken: {{ $hasApiKey ? 'true' : 'false' }},
    showInput: {{ $hasApiKey ? 'false' : 'true' }},
    tokenInput: '',
    saving: false,
    testing: false,
    syncing: false,
    syncMode: null,
    message: '',
    messageType: 'success',
    modelState: {{ \Illuminate\Support\Js::from($modelSync) }},

    async saveToken() {
        this.saving = true;
        this.message = '';
        try {
            const r = await fetch('{{ route('settings.chatgpt.save') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ api_key: this.tokenInput })
            });
            const d = await r.json();
            this.message = d.message;
            this.messageType = d.success ? 'success' : 'error';
            if (d.success) { this.hasToken = true; this.showInput = false; this.tokenInput = ''; }
        } catch (e) { this.message = 'Network error'; this.messageType = 'error'; }
        this.saving = false;
    },

    async testToken() {
        this.testing = true;
        this.message = '';
        try {
            const r = await fetch('{{ route('settings.chatgpt.test') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            });
            const d = await r.json();
            this.message = d.message;
            this.messageType = d.success ? 'success' : 'error';
        } catch (e) { this.message = 'Network error'; this.messageType = 'error'; }
        this.testing = false;
    },

    async syncModels(purge = false) {
        this.syncing = true;
        this.syncMode = purge ? 'purge' : 'sync';
        this.message = '';

        try {
            const r = await fetch(purge ? '{{ route('settings.chatgpt.models.purge-sync') }}' : '{{ route('settings.chatgpt.models.sync') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ purge_cache: purge })
            });
            const d = await r.json();
            this.message = d.message;
            this.messageType = d.success ? 'success' : 'error';
            if (d.data) {
                this.modelState = d.data;
            }
        } catch (e) {
            this.message = 'Network error';
            this.messageType = 'error';
        }

        this.syncing = false;
        this.syncMode = null;
    }
}">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">ChatGPT / OpenAI</h1>
        <p class="mt-1 text-sm text-gray-500">OpenAI API integration for GPT-powered article generation.</p>
    </div>

    <template x-if="message">
        <div class="mb-4 p-4 rounded-lg text-sm" :class="messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'">
            <span x-text="message"></span>
        </div>
    </template>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">API Key</h2>
        <p class="text-xs text-gray-400 mb-4">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 hover:underline inline-flex items-center gap-1">platform.openai.com <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg></a></p>

        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-4 text-xs text-gray-600">
            <h3 class="font-semibold text-gray-700 mb-2">Setup Instructions</h3>
            <ol class="list-decimal list-inside space-y-1">
                <li>Go to <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 hover:underline">platform.openai.com/api-keys</a></li>
                <li>Click <strong>Create new secret key</strong></li>
                <li>Copy the key (starts with <code class="bg-gray-200 px-1 rounded">sk-</code>)</li>
                <li>Paste it below and save</li>
            </ol>
        </div>

        <div class="flex items-center gap-3">
            <template x-if="!showInput && hasToken">
                <div class="flex items-center gap-3 flex-1">
                    <div class="flex-1 bg-gray-100 rounded-lg px-4 py-2.5 text-sm font-mono text-gray-600 border border-gray-200">{{ $maskedKey }}</div>
                    <button @click="showInput = true" class="px-4 py-2.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">Change</button>
                    <button @click="testToken()" :disabled="testing" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 disabled:opacity-50">
                        <svg x-show="testing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="testing ? 'Testing...' : 'Test Connection'"></span>
                    </button>
                </div>
            </template>
            <template x-if="showInput || !hasToken">
                <div class="flex items-center gap-3 flex-1">
                    <input type="text" x-model="tokenInput" class="flex-1 rounded-lg text-sm border-2 border-gray-300 bg-white px-4 py-2.5 font-mono focus:border-blue-500 focus:ring-2 focus:ring-blue-200" placeholder="sk-..." autocomplete="off">
                    <button @click="saveToken()" :disabled="!tokenInput || saving" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <svg x-show="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="saving ? 'Saving...' : 'Save'"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-3">Available Models</h2>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="text-xs text-gray-500 space-y-1">
                <p><span class="font-medium text-gray-700">Last updated:</span> <span x-text="modelState.last_synced_human || 'never'"></span></p>
                <p><span class="font-medium text-gray-700">Source:</span> <span x-text="modelState.source_label || 'Packaged Defaults'"></span></p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="syncModels(false)" :disabled="syncing" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 disabled:opacity-60">
                    <svg x-show="syncing && syncMode === 'sync'" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span>Get Available Models</span>
                </button>
                <button @click="syncModels(true)" :disabled="syncing" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 disabled:opacity-60">
                    <svg x-show="syncing && syncMode === 'purge'" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span>Purge Cache and Sync With Source</span>
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <template x-for="model in modelState.models" :key="model.id">
                <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2.5 text-sm">
                    <span class="font-medium text-gray-800" x-text="model.name"></span>
                    <span class="text-xs text-gray-400 font-mono" x-text="model.id"></span>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
