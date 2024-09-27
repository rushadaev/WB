<?php

use App\Http\Controllers\WildberriesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/wildberries/questions', [WildberriesController::class, 'getQuestions']);

 // const response = await axios.get(`${process.env.LARAVEL_API_URL}/api/users/telegram/${telegramId}`);
Route::get('/users/telegram/{telegramId}', [UserController::class, 'getUserByTelegramId']);
