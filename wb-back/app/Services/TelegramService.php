<?php

namespace App\Services;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class TelegramService
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new BotApi(config('telegram.bot_token'));
    }

    public function sendMessage($chatId, $message, $parseMode = 'HTML', $disableWebPagePreview = false, $replyToMessageId = null, InlineKeyboardMarkup $replyMarkup = null)
    {
        return $this->telegram->sendMessage($chatId, $message, $parseMode, $disableWebPagePreview, $replyToMessageId, $replyMarkup);
    }

    // Add more methods as needed
}