@extends('layouts.app')
@section('title', 'ChatGPT Settings')
@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    hasToken: @js($hasApiKey),
    showInput: @js(!$hasApiKey),
    maskedKey: @js($maskedKey),
    tokenInput: '',
    saving: false,
    testing: false,
    syncing: false,
    syncMode: null,
    message: '',
    messageType: 'success',
    modelState: {{ \Illuminate\Support\Js::from($modelSync) }},

    requestHeaders(includeJson = false) {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        };
        if (includeJson) headers['Content-Type'] = 'application/json';
        return headers;
    },

    async readJson(response) {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
            throw new Error(data.message || validation || 'Request failed.');
        }
        return data;
    },

    async saveToken() {
        const apiKey = this.tokenInput.trim();
        if (!apiKey) return;

        this.saving = true;
        this.message = '';
        try {
            const response = await fetch('{{ route('settings.chatgpt.save') }}', {
                method: 'POST',
                headers: this.requestHeaders(true),
                body: JSON.stringify({ api_key: apiKey }),
            });
            const data = await this.readJson(response);
            this.message = data.message || 'API key saved.';
            this.messageType = 'success';
            this.maskedKey = data.masked_key || this.maskedKey;
            this.hasToken = true;
            this.showInput = false;
            this.tokenInput = '';
        } catch (error) {
            this.message = error.message;
            this.messageType = 'error';
        }
        this.saving = false;
    },

    cancelEdit() {
        this.tokenInput = '';
        this.showInput = !this.hasToken;
    },

    async testToken() {
        this.testing = true;
        this.message = '';
        try {
            const response = await fetch('{{ route('settings.chatgpt.test') }}', {
                method: 'POST',
                headers: this.requestHeaders(),
            });
            const data = await this.readJson(response);
            this.message = data.message || 'OpenAI API access is active.';
            this.messageType = 'success';
        } catch (error) {
            this.message = error.message;
            this.messageType = 'error';
        }
        this.testing = false;
    },

    async syncModels(purge = false) {
        this.syncing = true;
        this.syncMode = purge ? 'purge' : 'sync';
        this.message = '';

        try {
            const response = await fetch(purge ? '{{ route('settings.chatgpt.models.purge-sync') }}' : '{{ route('settings.chatgpt.models.sync') }}', {
                method: 'POST',
                headers: this.requestHeaders(true),
                body: JSON.stringify({ purge_cache: purge }),
            });
            const data = await this.readJson(response);
            this.message = data.message || 'Model sync completed.';
            this.messageType = 'success';
            if (data.data) this.modelState = data.data;
        } catch (error) {
            this.message = error.message;
            this.messageType = 'error';
        }

        this.syncing = false;
        this.syncMode = null;
    }
}">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">OpenAI / ChatGPT / Codex</h1>
        <p class="mt-1 text-sm text-gray-500">Manage the OpenAI platform credential and provider status used by GPT-powered workflows.</p>
    </div>

    <div x-show="message" x-cloak class="rounded-lg border px-4 py-3 text-sm"
         :class="messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'">
        <span x-text="message"></span>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">API Key</h2>
                <p class="mt-1 text-xs text-gray-500">Store or replace the OpenAI API key, then verify access from this page.</p>
            </div>
            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="hasToken ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                <span x-text="hasToken ? 'Configured' : 'Not configured'"></span>
            </span>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer"
               class="inline-flex min-h-10 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-center text-sm font-medium text-blue-700 hover:bg-blue-100">
                API Keys
            </a>
            <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer"
               class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-100">
                Billing &amp; Credits
            </a>
            <a href="https://platform.openai.com/usage" target="_blank" rel="noopener noreferrer"
               class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-100">
                Usage
            </a>
        </div>

        <div class="border-t border-gray-200 pt-4 text-sm text-gray-600">
            <h3 class="font-semibold text-gray-800">Setup and credit refresh</h3>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Create an API key from <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:underline">OpenAI API Keys</a>.</li>
                <li>Add or confirm funds in <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:underline">Billing &amp; Credits</a>.</li>
                <li>Paste the key below, save it, then click <strong>Test API Status</strong>.</li>
                <li>Use <a href="https://platform.openai.com/usage" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:underline">Usage</a> to review provider-side consumption.</li>
            </ol>
            <p class="mt-2 text-xs text-gray-500">OpenAI does not expose account credit balance through this connection test. After adding credit, verify it in Billing &amp; Credits and rerun Test API Status here.</p>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <template x-if="!showInput && hasToken">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1 truncate rounded-lg border border-gray-200 bg-gray-100 px-4 py-2.5 font-mono text-sm text-gray-600"
                         x-text="maskedKey"
                         aria-label="Stored API key ending"></div>
                    <div class="grid shrink-0 grid-cols-2 gap-2 sm:flex">
                        <button type="button" @click="showInput = true"
                                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                            Replace Key
                        </button>
                        <button type="button" @click="testToken()" :disabled="testing"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-100 disabled:opacity-50">
                            <svg x-show="testing" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span x-text="testing ? 'Testing...' : 'Test API Status'"></span>
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="showInput || !hasToken">
                <div class="space-y-3">
                    <div>
                        <label for="openai-api-key" class="mb-1 block text-sm font-medium text-gray-700">OpenAI API Key</label>
                        <input id="openai-api-key" type="password" x-model="tokenInput" maxlength="512"
                               class="w-full min-w-0 rounded-lg border-2 border-gray-300 bg-white px-4 py-2.5 font-mono text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                               placeholder="Paste the full OpenAI API key" autocomplete="new-password">
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex">
                        <button type="button" @click="saveToken()" :disabled="!tokenInput.trim() || saving"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            <svg x-show="saving" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span x-text="saving ? 'Saving...' : 'Save API Key'"></span>
                        </button>
                        <button type="button" @click="cancelEdit()" x-show="hasToken"
                                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">Available Models</h2>
                <div class="mt-2 space-y-1 text-xs text-gray-500">
                    <p><span class="font-medium text-gray-700">Last updated:</span> <span x-text="modelState.last_synced_human || 'never'"></span></p>
                    <p><span class="font-medium text-gray-700">Source:</span> <span x-text="modelState.source_label || 'Packaged Defaults'"></span></p>
                </div>
            </div>
            <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 lg:w-auto">
                <button type="button" @click="syncModels(false)" :disabled="syncing"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-60">
                    <svg x-show="syncing && syncMode === 'sync'" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span>Refresh Models</span>
                </button>
                <button type="button" @click="syncModels(true)" :disabled="syncing"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-60">
                    <svg x-show="syncing && syncMode === 'purge'" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span>Purge Cache &amp; Refresh</span>
                </button>
            </div>
        </div>

        <div class="mt-4 space-y-2">
            <template x-for="model in (modelState.models || [])" :key="model.id">
                <div class="grid min-w-0 grid-cols-1 gap-1 rounded-lg bg-gray-50 px-4 py-2.5 text-sm sm:grid-cols-2 sm:items-center">
                    <span class="min-w-0 font-medium text-gray-800" x-text="model.name"></span>
                    <span class="min-w-0 break-all font-mono text-xs text-gray-400 sm:text-right" x-text="model.id"></span>
                </div>
            </template>
        </div>
    </section>
</div>
@endsection
