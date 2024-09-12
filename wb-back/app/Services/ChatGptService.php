<?php
namespace App\Services;

use App\Models\Feedback;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ChatGptService
{
    public function generateResponse(Feedback $feedback): void
    {
        $user = $feedback->cabinet->user;
        $advertisement = $feedback->cabinet->settings['onboarding']['advertisement_message'] ?? null;
        $callToAction = $feedback->cabinet->settings['onboarding']['call_to_action'] ?? null;
        $productName = $feedback->productDetails['productName'] ?? 'товар';
        $productDescription = $feedback->productDetails['description'] ?? 'описание товара';
        $userName = $feedback->userName ?? 'клиент';
        $productValuation = $feedback->productValuation ?? '? звезд';

        if ($user->tokens <= 0) {
            return;
        }

        try {
            $brand = $feedback->productDetails['brandName'] ?? 'бренд';

            $prompt = "# промпт

Выступай в роли представителя службы поддержки бренда {$brand} на маркетплейсе Wildberries. Ваше задание — сгенерировать ответ на отзыв клиента о продукте на маркетплейсе Wildberries. Ответ должен быть вежливым и направленным на решение проблем, если они есть, или на выражение благодарности за положительный отзыв. Используйте дружелюбный и профессиональный тон, упомяни название товара и поблагодарите клиента за покупку. Перед написанием ответа, определите ключевые SEO слова из описания товара, которые могут быть полезны для составления ответа, и интегрируйте их в текст. Обращайтесь к клиенту всегда по имени. В своем ответе укажите только ответ на отзыв. 

С уважением, {$brand}.

важные правила:

1. В ответе на отзывы нельзя упоминать Wildberies или любое другое название маркетплейса. 
2. если клиент ругается по поводу доставки, то объясните, что вопросы доставки и логистики находятся в ведении Wildberries, и продавец не может повлиять на сроки доставки. Ответ должен быть кратким и поддерживающим.";

            
            $prompt = Setting::first()->prompt ?? $prompt;

            $context = "# Контекст:

Название товара: {$productName}

Данные по отзыву

Имя: {$userName}

Оценка: {$productValuation}

Отзыв: {$feedback->text}";

            $context = Setting::first()->context ?? $context;


            // Replace variables with real values
            $replacements = [
                '{$brand}' => $brand,
                '{$productName}' => $productName,
                '{$userName}' => $userName,
                '{$productValuation}' => $productValuation,
                '{$feedback->text}' => $feedback->text,
            ];
            
            $finalPrompt = str_replace(array_keys($replacements), array_values($replacements), $prompt);
            $finalContext = str_replace(array_keys($replacements), array_values($replacements), $context);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'max_tokens' => 200,
                'messages' => [
                    ['role' => 'system', 'content' => $finalPrompt],
                    ['role' => 'user', 'content' => $finalContext],
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

            Log::info('Request to Chat GPT', ['feedback_id' => $feedback->id, 'text' => $feedback->text]);

            $feedbackResponse = json_decode($response['choices'][0]['message']['content'] ?? '', true);
            Log::info('Response from Chat GPT', ['feedback_id' => $feedback->id, 'response' => $feedbackResponse]);

            if (!$feedbackResponse || !isset($feedbackResponse['message'], $feedbackResponse['mood'])) {
                return;
            }

            $allowedMoods = ['positive', 'negative'];
            $mood = in_array($feedbackResponse['mood'], $allowedMoods) ? $feedbackResponse['mood'] : 'neutral';

            $feedback->update([
                'answer' => $feedbackResponse['message'],
                'status' => 'ready_to_send',
                'mood' => $mood,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching ChatGPT response: ' . $e->getMessage());
        }
    }
}