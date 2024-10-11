<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class CheckBookingCoefficients implements ShouldQueue
{
    use Queueable, Dispatchable;
    public $timeout = 300;

    protected $botToken;

    /**
     * Create a new job instance.
     */
    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
         // Define the number of iterations within the minute (15 for every 4 seconds)
         $iterations = 15;
         $intervalSeconds = 4;

         for ($i = 0; $i < $iterations; $i++) {
            // Step 1: Fetch all active notifications
            $notifications = Notification::where([['status', 'started'], ['settings->isBooking', true]])->get();


             // Step 4: Iterate over each notification to check for changes
             foreach ($notifications as $notification) {
                CheckCoefficientAndSendMessage::dispatch($notification, $this->botToken);
            }

            //Log the execution for debugging
            \Log::info('Coefficients check iteration ' . ($i + 1) . ' completed at ' . now());
            // Sleep for 4 seconds before the next iteration
            sleep($intervalSeconds);
         }
    }
}
