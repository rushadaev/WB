<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendUserNotificationMessage;

class AuthenticateTelegramUser
{
    public function handle(Request $request, Closure $next)
    {
        $webhookData = $request->all();
        
        Log::info('Authorizing processing', ['INSIDE middleware' => $webhookData]);

        // Extract chat ID from message or callback_query
        if (isset($webhookData['message']['from']['id'])) {
            $chatId = $webhookData['message']['from']['id'];
            $username = $webhookData['message']['from']['username'] ?? null;
        } elseif (isset($webhookData['callback_query']['from']['id'])) {
            $chatId = $webhookData['callback_query']['from']['id'];
            $username = $webhookData['callback_query']['from']['username'] ?? $chatId;
        } elseif (isset($webhookData['pre_checkout_query']['from']['id'])) {
            $chatId = $webhookData['pre_checkout_query']['from']['id'];
            $username = $webhookData['pre_checkout_query']['from']['username'] ?? null;
        } else {
            return response()->json(['error' => 'Unauthorized. Chat ID not provided.'], 401);
        }
        
        // Create or find the user
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            [
                'name' => $username, // username
                'email' => $chatId . '@telegram.com', // Default email
                'password' => Hash::make('default_password'), // Default password
            ]
        );

        //First login basically
        if (is_null($user->subscription_until)) {
            $user->subscription_until = now()->addDays(3);
            $user->save();
            $message = "#подписка\n@{$username} подписался на бота";
            SendUserNotificationMessage::dispatch($message, 'HTML');
        }

        Auth::login($user);

        return $next($request);
    }
}