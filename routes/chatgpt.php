<?php

use hexa_package_chatgpt\Http\Controllers\ChatGptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ChatGPT Package Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {
    // Settings
    Route::get('/settings/chatgpt', [ChatGptController::class, 'settings'])->name('settings.chatgpt');
    Route::post('/settings/chatgpt/save', [ChatGptController::class, 'saveKey'])->name('settings.chatgpt.save');
    Route::post('/settings/chatgpt/test', [ChatGptController::class, 'testKey'])->name('settings.chatgpt.test');
    Route::post('/settings/chatgpt/models/sync', [ChatGptController::class, 'syncModels'])->name('settings.chatgpt.models.sync');
    Route::post('/settings/chatgpt/models/purge-sync', [ChatGptController::class, 'purgeAndSyncModels'])->name('settings.chatgpt.models.purge-sync');

    // Raw dev view
    Route::hexaRawPage('/raw-chatgpt', [ChatGptController::class, 'raw'], 'chatgpt.index', [
        'package' => 'chatgpt',
        'label' => 'Playground',
        'sortOrder' => 10,
    ]);

    // API endpoints
    Route::post('/chatgpt/chat', [ChatGptController::class, 'chat'])->name('chatgpt.chat');
});
