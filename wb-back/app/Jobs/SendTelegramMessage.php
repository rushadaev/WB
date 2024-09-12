<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\UsesTelegram;
use App\Models\Cabinet;
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
        public $cabinetId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $botToken = $this->botToken ?? config('telegram.bot_token');
        $telegram = $this->useTelegram();
        $telegram->setBotToken($botToken);
    
        try {
            $telegram->sendMessage($this->telegramId, $this->message, $this->parse_mode, false, null, $this->keyboard);
        } catch (\TelegramBot\Api\HttpException $e) {
            if ($e->getCode() === 403 && strpos($e->getMessage(), 'bot was blocked by the user') !== false) {
                // Log specific case when the bot is blocked by the user
                if($this->cabinetId) {
                    $cabinet = Cabinet::find($this->cabinetId);

                    $settings = $cabinet->settings ?? [];  // Default to an empty array if settings are null
                    $groupId = $settings['group_chat_id'] ?? null;  // Default to null if not set
    
                    if ($groupId) {
                        //Set cabinet to null
                        $cabinet->settings = [];
                        $cabinet->save();
                    }
                }
                \Log::error("Telegram Bot was blocked by the user: {$this->telegramId}");
            } else {
                // Log any other TelegramBot API exception
                \Log::error("Telegram API Error: {$e->getMessage()}", ['exception' => $e]);
            }
        } catch (\Exception $e) {
            // Log any other general exception
            \Log::error("An error occurred: {$e->getMessage()}", ['exception' => $e]);
        }
    }
}