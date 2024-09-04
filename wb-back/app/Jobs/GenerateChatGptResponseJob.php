<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Feedback;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class GenerateChatGptResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $feedback;

    /**
     * Create a new job instance.
     */
    public function __construct(Feedback $feedback)
    {
        $this->feedback = $feedback;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->feedback->cabinet->user;

        if ($user->tokens <= 0) {
            return;
        }
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'max_tokens' => 200,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник продавца в маркеплейсе Wildberries. Твоя задача давать максимально сгалаженные ответы на вопросы и отзывы под товарами. Твои ответы будут вставлены на сайте. Тебя зовут Алексей. Вопрос пользователя:'],
                    ['role' => 'user', 'content' => $this->feedback->text],
                ],
            ]);

            $answer = $response['choices'][0]['message']['content'] ?? null;
            if(!$answer){
                return;
            }
            // Update feedback with the response
            $this->feedback->update([
                'answer' => $answer,
                'status' => 'ready_to_send',
            ]);
            
            // Decrease user's token count after success
            $user->tokens = $user->tokens - 1;
            $user->save();

            Log::info('Request to Chat GPT succesfull', ['feedback_id' => $this->feedback->id, 'answer' => $answer]);
        } catch (\Exception $e) {
            Log::error('Error fetching ChatGPT response: ' . $e->getMessage());
        }
    }
}