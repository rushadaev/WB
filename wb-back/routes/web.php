<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['telegram.auth'])->group(function () {
    Route::post('/webhook/telegram/', [TelegramController::class, 'handleWebhook']);
});