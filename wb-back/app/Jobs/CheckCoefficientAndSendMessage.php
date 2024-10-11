<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NodeApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckCoefficientAndSendMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notification;
    protected $botToken;

    /**
     * Create a new job instance.
     *
     * @param Notification $notification
     */
    public function __construct(Notification $notification, string $botToken)
    {
        $this->notification = $notification;
        $this->botToken = $botToken;
    }

    /**
     * Execute the job.
     *
     * @param NodeApiService $nodeApiService
     * @return void
     */
    public function handle(NodeApiService $nodeApiService)
    {
        try {
            // Decode the JSON settings
            $settings = $this->notification->settings;

            $cabinetId = $settings['cabinetId'];
            $preorderId = $settings['preorderId'];
            $coefficientToCheck = $settings['coefficient'];
            $warehouseId = $settings['warehouseId'];
            $monopalletCount = $settings['monopalletCount'] ?? null;

            // Call the API to get time slots
            $response = $nodeApiService->listTimeSlots($cabinetId, $preorderId);

            // Check if the response is successful
            if ($response['message'] !== 'Fetched acceptance costs and delivery date successfully.') {
                Log::warning('Unexpected API response for Notification ID: ' . $this->notification->id);
                return;
            }

            $acceptanceCosts = $response['data']['acceptanceCosts']['costs'];

            Log::info('Timeslots Fetched Succesfully'.$this->notification->id, ['data', $acceptanceCosts]);

            // Iterate through the costs to find a matching coefficient
            foreach ($acceptanceCosts as $cost) {
                if ($cost['coefficient'] == $coefficientToCheck) {
                    $deliveryDate = $cost['date'];
                    $user = $this->notification->user;

                    // Dispatch the BookTimeSlotJob
                    BookTimeSlotJob::dispatch(
                        $cabinetId,
                        $preorderId,
                        $warehouseId,
                        $deliveryDate,
                        $monopalletCount,
                        $user->telegram_id,
                        $user->id,
                        $this->botToken
                    );

                    Log::info('BookTimeSlotJob dispatched for Notification ID: ' . $this->notification->id);
                    $notification->status = 'finished';
                    $notification->save();

                    break; // Exit the loop once the matching coefficient is found
                }
            }

        } catch (\Exception $e) {
            Log::error('Error in CheckCoefficientAndSendMessage Job: '.$this->notification->id.' - ' . $e->getMessage());
            // Optionally, you can retry or handle the exception as needed
        }
    }
}
