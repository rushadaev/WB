<?php

namespace App\Services;

use TelegramBot\Api\Client;
use Illuminate\Support\Facades\Log;
use App\Traits\UsesTelegram;

class TelegramNotificationService
{
    use UsesTelegram;

    public static function notify($telegramId, $message)
    {
        // Creating a new instance to use the trait method
        $instance = new self();
        $telegram = $instance->useTelegram();

        try {
            $telegram->sendMessage($telegramId, $message, 'HTML', false, null, null);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'telegram_id' => $telegramId,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }
}