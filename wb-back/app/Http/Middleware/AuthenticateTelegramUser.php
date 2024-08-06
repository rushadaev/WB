<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticateTelegramUser
{
    public function handle(Request $request, Closure $next)
    {
        $webhookData = $request->all();
        
        Log::info('Authorizing processing', ['INSIDE middleware' => $webhookData]);

        // Extract chat ID from message or callback_query
        if (isset($webhookData['message']['from']['id'])) {
            $chatId = $webhookData['message']['from']['id'];
        } elseif (isset($webhookData['callback_query']['from']['id'])) {
            $chatId = $webhookData['callback_query']['from']['id'];
        } elseif (isset($webhookData['pre_checkout_query']['from']['id'])) {
            $chatId = $webhookData['pre_checkout_query']['from']['id'];
        } else {
            return response()->json(['error' => 'Unauthorized. Chat ID not provided.'], 401);
        }
        
        // Create or find the user
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            [
                'name' => 'TelegramUser_' . $chatId, // Default name
                'email' => $chatId . '@telegram.com', // Default email
                'password' => Hash::make('default_password'), // Default password
            ]
        );

        // If user is found but subscription_until is not set, update it
        if (!$user->wasRecentlyCreated && is_null($user->subscription_until)) {
            $user->subscription_until = now()->addDays(3);
            $user->save();
        }

        Auth::login($user);

        return $next($request);
    }
}