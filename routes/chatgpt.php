<?php

use hexa_package_chatgpt\Http\Controllers\ChatGptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ChatGPT Package Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {
    // Raw dev view
    Route::get('/raw-chatgpt', [ChatGptController::class, 'raw'])->name('chatgpt.index');

    // API endpoints
    Route::post('/chatgpt/chat', [ChatGptController::class, 'chat'])->name('chatgpt.chat');
});
