<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TelegramControllerSupplies;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['telegram.auth'])->group(function () {
    Route::post('/webhook/telegram/', [TelegramController::class, 'handleWebhook']);
    Route::post('/webhook/telegram/supplies', [TelegramControllerSupplies::class, 'handleWebhookSupplies']);
});