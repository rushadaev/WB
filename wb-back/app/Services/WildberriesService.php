<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WildberriesService
{
    protected $apiUrl = 'https://feedbacks-api.wildberries.ru/api/v1/questions';
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('wildberries.api_key'); // Store your API key in the .env file
    }

    public function getQuestions($isAnswered = false, $take = 10000, $skip = 0, $order = 'dateDesc', $nmId = null, $dateFrom = null, $dateTo = null)
    {
        $query = [
            'isAnswered' => $isAnswered,
            'take' => $take,
            'skip' => $skip,
            'order' => $order,
        ];

        if ($nmId) {
            $query['nmId'] = $nmId;
        }

        if ($dateFrom) {
            $query['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $query['dateTo'] = $dateTo;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get($this->apiUrl, $query);

            if ($response->successful()) {
                return [
                    'data' => $response->json()['data'],
                    'error' => false,
                    'errorText' => '',
                ];
            } else {
                Log::error('Wildberries API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'data' => null,
                    'error' => true,
                    'errorText' => 'API request failed',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Wildberries API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'data' => null,
                'error' => true,
                'errorText' => 'An error occurred while making the API request',
            ];
        }
    }
}