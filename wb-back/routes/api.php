<?php

use App\Http\Controllers\WildberriesController;

Route::get('/wildberries/questions', [WildberriesController::class, 'getQuestions']);