<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Cabinet;
use Carbon\Carbon;
use App\Models\Feedback;
use App\Services\TelegramService;
use App\Jobs\SendTelegramMessage;
use App\Services\ChatGptService;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class SendFeedbacksToTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cabinetId;
    public $callToAction;
    public $advertisement;
    public $suffixMessage;

    /**
     * Create a new job instance.
     */
    public function __construct($cabinetId)
    {
        $this->cabinetId = $cabinetId;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegramService, ChatGptService $chatGptService): void
    {
        $cabinet = Cabinet::find($this->cabinetId);
        $user = $cabinet->user;
        if (!$cabinet) {
            Log::error('Cabinet not found: ' . $this->cabinetId);
            return;
        }
    
        $settings = $cabinet->settings ?? [];  // Default to an empty array if settings are null
        $groupId = $settings['group_chat_id'] ?? null;  // Default to null if not set
    
        if (!$groupId) {
            $this->sendReminderToSetupGroup($cabinet->user->telegram_id, $this->cabinetId);
            Log::error('Group chat ID not set for cabinet: ' . $this->cabinetId);
            return;
        }

        $this->advertisement = $settings['onboarding']['advertisement_message'] ?? null;
        $this->callToAction = $settings['onboarding']['call_to_action'] ?? null;

        $this->suffixMessage = "\n\n{$this->advertisement}\n{$this->callToAction}";
    
        $feedbacks = Feedback::where('cabinet_id', $cabinet->id)
                     ->where('status', 'ready_to_send')
                     ->inRandomOrder()
                     ->take(1)
                     ->get();

        // $botEnabled = $settings['enabled'] ?? false;
        // if(!$botEnabled){
        //     Log::info('Bot is disabled: ' . $this->cabinetId);
        //     return; 
        // } 

        foreach ($feedbacks as $feedback) {
            //TODO: THIS IS A MOCK IMPLEMENTATION, REPLACE WITH REAL LOGIC
            $chatGptService->generateResponse($feedback);

            $onboarding = $cabinet->settings['onboarding'] ?? null;
            if (!$onboarding) {
                Log::error('Onboarding settings not found for cabinet: ' . $this->cabinetId);
                return;
            }
        
            $mode = $onboarding['mode'] ?? 'manual_confirmation'; // Default to manual_confirmation if not set
            $productValuation = $feedback->productValuation;
            $mood = $feedback->mood; //positive, negative, neutral
        
            Log::info("Sending feedback to Telegram: " . $feedback->id);
            switch ($mode) {
                case 'combined':
                    // Combined mode: Automatically respond to positive, confirm negative
                    if ($mood === 'positive') {
                        $this->sendToWildberries($feedback, $groupId, $user);
                    } else {
                        $this->sendToGroup($feedback, $groupId);
                    }
                    break;
        
                case 'auto_response':
                    // Auto response to all feedbacks
                    $this->sendToWildberries($feedback, $groupId, $user);
                    break;
        
                case 'positive_response':
                    // Respond to positive feedbacks (4-5 stars), send negative for manual response
                    if ($productValuation >= 4) {
                        $this->sendToWildberries($feedback, $groupId, $user);
                    } else {
                        $this->sendToGroup($feedback, $groupId);
                    }
                    break;
        
                case 'manual_confirmation':
                    // Generate response and let the user confirm or modify
                    $this->sendToGroup($feedback, $groupId);
                    break;
        
                default:
                    // Default to manual confirmation if no valid mode is found
                    $this->sendToGroup($feedback, $groupId);
                    break;
            }
        }
    }

    protected function sendToWildberries($feedback, $groupId, $user)
    {
        // Mock logic for sending feedback to Wildberries
        Log::info("Sending feedback to Wildberries: " . $feedback->id);

        // Update feedback status to 'sent'
        
        //TODO:Returnback!!!
        // $feedback->status = 'sent';

        //Decrease user tokens
        $user->tokens = $user->tokens - 1;
        $user->save();

        $message = $this->formatMessage($feedback, 'Ответ недоступен, пожалуйста, нажмите кнопку "Другой"') . $this->suffixMessage;

        SendTelegramMessage::dispatch($groupId, "Ответ на отзыв {$feedback->id} был отправлен в WB автоматически.\n\n" . $message, 'HTML', null);
        $feedback->save();
    }

    protected function sendToGroup($question, $groupId)
    {
        //$generatedResponse = $this->generateGptResponse($question['text'].'Товар:'.$question['productDetails']['productName']);
        $questionKeyboard = new InlineKeyboardMarkup([
            [['text' => '🔄 Другой', 'callback_data' => "change_answer_{$question->id}"], ['text' => '✅Отправить', 'callback_data' => "accept_answer_{$question->id}"]],
        ]);
        $message = $this->formatMessage($question, 'Ответ недоступен, пожалуйста, нажмите кнопку "Другой"') . $this->suffixMessage;
        SendTelegramMessage::dispatch($groupId, $message, 'HTML', $questionKeyboard, null, $this->cabinetId);
    }

    protected function sendReminderToSetupGroup($chatId, $cabinetId)
    {
        $message = 'Пожалуйста, настройте групповой чат для отправки отзывов';
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🔧 Настроить', 'callback_data' => 'welcome_add_group_' . $cabinetId]],
        ]);
        SendTelegramMessage::dispatch($chatId, $message, 'HTML', $keyboard, null, $cabinetId);
    }

    protected function formatMessage($question, $generatedResponse)
    {
        $createdDate = Carbon::parse($question['createdDate'])->locale('ru')->isoFormat('LLL');
        $supplierName = str_replace('Индивидуальный предприниматель', 'ИП', $question['productDetails']['supplierName']);
        $supplierName = htmlspecialchars($supplierName);
        $userName = $question['userName']; 
        $productName = htmlspecialchars($question['productDetails']['productName']);
        $article = htmlspecialchars($question['productDetails']['imtId']);
        $questionText = htmlspecialchars($question['text']);


        
        $generatedResponseText = htmlspecialchars($question['answer'] ?? $generatedResponse);
        $mood = $question['mood'];
        $moodText = match ($mood) {
            'positive' => 'Позитивный',
            'negative' => 'Негативный',
            'neutral' => 'Нейтральный',
            default => 'Неизвестно',
        };
        $moodEmoji = match ($mood) {
            'positive' => '😊',
            'negative' => '😡',
            'neutral' => '😐',
            default => '😐',
        };

        return "<b>Дата:</b> $createdDate\n$supplierName\n<b>Артикул:</b> $article\n<b>📦 Товар:</b> $productName\n\n<b>💬 {$userName}:\n</b>$questionText\n<b>⭐ Оценка:</b> $question->productValuation\n<b>{$moodEmoji} Настроение:</b> $moodText\n\n<b>🤖 #Предлагаемый_ответ:\n\n</b><code>$generatedResponseText</code>";
    }
}
