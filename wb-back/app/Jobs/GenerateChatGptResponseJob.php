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
        $advertisement = $this->feedback->cabinet->settings['onboarding']['advertisement_message'] ?? null;
        $callToAction = $this->feedback->cabinet->settings['onboarding']['call_to_action'] ?? null;
        $productName = $this->feedback->productDetails['productName'] ?? 'товар';
        $productDescription = $this->feedback->productDetails['description'] ?? 'описание товара';
        $userName = $this->feedback->userName ?? 'клиент';
        $productValuation = $this->feedback->productValuation ?? '? звезд';

        if ($user->tokens <= 0) {
            return;
        }
        try {
            $brand = $this->feedback->productDetails['brandName'] ?? 'бренд';

            $prompt = "# промпт

Выступай в роли представителя службы поддержки бренда {$brand} на маркетплейсе Wildberries.  Ваше задание — сгенерировать ответ на отзыв клиента о продукте на маркетплейсе Wildberries. Ответ должен быть вежливым и направленным на решение проблем, если они есть, или на выражение благодарности за положительный отзыв. Используйте дружелюбный и профессиональный тон, упомяни название товара и поблагодарите клиента за покупку. Перед написанием ответа, определите ключевые SEO слова из описания товара, которые могут быть полезны для составления ответа, и интегрируйте их в текст. Обращайтесь к клиенту всегда по имени. В своем ответе укажите только ответ на отзыв. 

С уважение {$brand}.

важные правила:

1. В ответе на отзывы нельзя упоминать Wildberies или любое другое название маркетплейса. 
2. если клиент ругается по поводу доставки, то объясните, что вопросы доставки и логистики находятся в ведении Wildberries, и продавец не может повлиять на сроки доставки. Ответ должен быть кратким и поддерживающим.
";
$context = "# Контекст:

Название товара: {$productName}

Данные по отзыву

Имя: {$userName} 

Оценка: {$productValuation} 

Отзыв: {$this->feedback->text}";
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'max_tokens' => 200,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $context],
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