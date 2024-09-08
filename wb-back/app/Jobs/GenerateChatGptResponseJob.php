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
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'feedback_response',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => [
                                    'type' => 'string',
                                ],
                                'mood' => [
                                    'type' => 'string',
                                    'enum' => ['positive', 'negative'],
                                ],
                            ],
                            'additionalProperties' => false,
                            'required' => ['message', 'mood'],
                        ],
                    ],
                ],
            ]);
            \Log::info('Request to Chat GPT', ['feedback_id' => $this->feedback->id, 'text' => $this->feedback->text]);
            
            // Ensure response exists and is structured as expected
            $feedbackResponse = json_decode($response['choices'][0]['message']['content'] ?? '', true);
            \Log::info('Response from Chat GPT', ['feedback_id' => $this->feedback->id, 'response' => $feedbackResponse]);
            
            if (!$feedbackResponse || !isset($feedbackResponse['message'], $feedbackResponse['mood'])) {
                return;
            }
            
            // Validate the mood is one of the allowed values
            $allowedMoods = ['positive', 'negative'];
            $mood = in_array($feedbackResponse['mood'], $allowedMoods) ? $feedbackResponse['mood'] : 'neutral';
            
            // Update feedback with the structured response
            $this->feedback->update([
                'answer' => $feedbackResponse['message'],
                'status' => 'ready_to_send',
                'mood' => $mood,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching ChatGPT response: ' . $e->getMessage());
        }
    }
}