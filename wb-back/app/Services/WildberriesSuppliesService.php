<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WildberriesSuppliesService
{
    protected $apiUrl = 'https://supplies-api.wildberries.ru/api/v1';
    protected $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getWarehouses()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get("{$this->apiUrl}/warehouses");

            if ($response->successful()) {
                return [
                    'data' => $response->json(),
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

    public function getAcceptanceCoefficients($warehouseIDs = null)
    {
        $query = [];

        if ($warehouseIDs) {
            $query['warehouseIDs'] = $warehouseIDs;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get("{$this->apiUrl}/acceptance/coefficients", $query);

            if ($response->successful()) {
                return [
                    'data' => $response->json(),
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