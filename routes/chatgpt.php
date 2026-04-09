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

    // Raw dev view
    Route::get('/raw-chatgpt', [ChatGptController::class, 'raw'])->name('chatgpt.index');

    // API endpoints
    Route::post('/chatgpt/chat', [ChatGptController::class, 'chat'])->name('chatgpt.chat');
});
