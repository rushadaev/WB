<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook/telegram', [TelegramController::class, 'handleWebhook']);