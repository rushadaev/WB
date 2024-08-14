<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\UsesTelegram;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class SendTelegramMessage implements ShouldQueue
{
    use Queueable;
    use Dispatchable;
    use UsesTelegram;
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $telegramId,
        public string $message,
        public string $parse_mode,
        public $keyboard = null,
        public $botToken = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $questionKeyboard = new InlineKeyboardMarkup([
        //     [['text' => '✅Принять ответ', 'callback_data' => "accept_answer"]],
        //     [['text' => '✍🏻Изменить ответ', 'callback_data' => "change_answer"]],
        //     [['text' => '💩Удалить вопрос', 'callback_data' => "delete_question"]],
        // ]);

        $botToken = $this->botToken ?? config('telegram.bot_token');
        $telegram = $this->useTelegram();
        $telegram->setBotToken($botToken);

        $telegram->sendMessage($this->telegramId, $this->message, $this->parse_mode, false, null, $this->keyboard);
    }
}