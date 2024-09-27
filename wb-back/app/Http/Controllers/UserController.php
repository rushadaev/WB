<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{

    public function getUserByTelegramId($telegramId)
    {
        $user = Cache::remember('user_telegram_id_' . $telegramId, 300, function () use ($telegramId) {
            return User::where('telegram_id', $telegramId)->first()->toArray();
        });

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($user);
    }
}
