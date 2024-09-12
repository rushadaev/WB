<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use App\Jobs\SendTelegramMessage;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use App\Models\Cabinet;
use App\Models\Feedback;
use App\Traits\UsesWildberries;


class FetchFeedbacksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UsesWildberries;

    protected $cabinetId;

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
    public function handle(): void
    {
        $cabinet = Cabinet::find($this->cabinetId);
        $settings = $cabinet->settings ?? [];  // Default to an empty array if settings are null
        $groupId = $settings['group_chat_id'] ?? null;  // Default to null if not set

        $apiKey = $cabinet->getFeedbackApiKey();
        $user = $cabinet->user; 
        if($user->tokens <= 0){
            Log::info('User has no tokens left', ['user_id' => $user->id]);
             //$generatedResponse = $this->generateGptResponse($question['text'].'Товар:'.$question['productDetails']['productName']);
            $questionKeyboard = new InlineKeyboardMarkup([
                [['text' => '🔝Пополнить баланс', 'callback_data' => "welcome_pay"]],
            ]);
            $message = "У вас закончились токены, пополните баланс для продолжения работы";
            SendTelegramMessage::dispatch($groupId, $message, 'HTML', $questionKeyboard); 
        }
        
        if (!$cabinet) {
            Log::error('Cabinet not found: ' . $this->cabinetId);
            return;
        }

        if (!$apiKey) {
            Log::error('Feedback API key not found for cabinet: ' . $this->cabinetId);
            return;
        }
        
        $feedbacks = $this->useWildberries($apiKey, $user)->getFeedbacks();
        if ($feedbacks['error']) {
            Log::error('Error fetching feedbacks: ' . $feedbacks['errorText']);
            return;
        }

        Log::info('info', ['test' => $feedbacks]);
        
        foreach ($feedbacks['data']['feedbacks'] as $feedback) {
            // Try to find existing feedback
            $feedbackModel = Feedback::where('feedback_id', $feedback['id'])->first();
        
            if (!$feedbackModel) {
                // Feedback doesn't exist, create a new record
                $feedbackModel = Feedback::create([
                    'feedback_id' => $feedback['id'],
                    'cabinet_id' => $cabinet->id,
                    'text' => $feedback['text'],
                    'productValuation' => $feedback['productValuation'],
                    'createdDate' => $feedback['createdDate'],
                    'answer' => 'Pending...', // Default value for new feedback
                    'status' => 'processing',   // Default status for new feedback
                    'productDetails' => $feedback['productDetails'],
                    'photoLinks' => $feedback['photoLinks'],
                    'wasViewed' => $feedback['wasViewed'],
                    'userName' => $feedback['userName'],
                    'color' => $feedback['color'],
                    'subjectId' => $feedback['subjectId'],
                    'subjectName' => $feedback['subjectName'],
                ]);
                if($user->tokens > 0){
                    // Since it's a new feedback, we need to get the ChatGPT response
                    GenerateChatGptResponseJob::dispatch($feedbackModel);
                }
            } else {
                // Feedback exists, check if answer is already populated
                if (empty($feedbackModel->answer) || $feedbackModel->answer === 'Pending...') {
                    // Answer is not yet populated, dispatch job to fetch the response from ChatGPT
                    if($user->tokens > 0){
                        GenerateChatGptResponseJob::dispatch($feedbackModel);
                    }
                } 
                // No update needed if feedback is already populated with a response
            }
        }
    }
}