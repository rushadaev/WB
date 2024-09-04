<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\SendFeedbacksToTelegramJob;
use App\Models\Cabinet;
use Illuminate\Support\Facades\Log;

class DispatchFeedbackSendJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // $cabinets = Cabinet::all();
        // foreach ($cabinets as $cabinet) {
        //     SendFeedbacksToTelegramJob::dispatch($cabinet->id);
        // }
        for ($i = 0; $i < 6; $i++) {
            // Run the feedback_send command
            // Artisan::call('feedback_send');

            // Log the execution for debugging
            Log::info('feedback_send job ran at ' . now());

            // Sleep for 10 seconds
            sleep(10);
        }
    }
}
