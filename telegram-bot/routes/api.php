<?php

use Illuminate\Support\Facades\Route;
use Telegram\Bot\Controllers\WebhookController;

Route::prefix('api/telegram-bot')
    ->middleware('api')
    ->group(function() {
        Route::post('webhook', [WebhookController::class, 'index'])->name('webhook');
    });
