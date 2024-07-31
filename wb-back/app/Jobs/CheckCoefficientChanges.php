<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\UsesWildberriesSupplies;
use Illuminate\Support\Facades\Log;
use App\Traits\UsesTelegram;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Carbon\Carbon;


class CheckCoefficientChanges implements ShouldQueue
{
    use Queueable;
    use Dispatchable;
    use UsesTelegram;
    use UsesWildberriesSupplies;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $notification,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $settings = $this->notification->settings;
        // TelegramInspire::dispatch('782919745', $notification, 'HTML');

        $user = User::where('telegram_id', $settings['chatId'])->first();
        if (!$user) {
            Log::error('User not found for chat ID: ' . $settings['chatId']);
            return;
        }

        $apiKey = $user->getSuppliesApiKey();
        if (!$apiKey) {
            Log::error('No API key for user: ' . $user->id);
            return;
        }

        // Fetch the latest coefficients from the API
        $response = $this->useWildberriesSupplies($apiKey)->getAcceptanceCoefficients($settings['warehouseId']);
        if ($response['error']) {
            Log::error('Error fetching coefficients: ' . $response['errorText']);
            return;
        }

        $coefficients = $response['data'];
        $trackedCoefficient = $settings['coefficient'];
        
        foreach ($coefficients as $coefficient) {
            // Parse the date from the coefficient object
            $coefficientDate = Carbon::parse($coefficient['date']);
            // Parse the checkUntilDate from settings
            $checkUntilDate = Carbon::parse($settings['checkUntilDate']);
            // Filter coefficients by date less than or equal to checkUntilDate
            if ($coefficientDate->lessThan($checkUntilDate)) {
                if (isset($coefficient['boxTypeID']) && $coefficient['boxTypeID'] == $settings['boxTypeId']) {

                    // $this->notifyUser($user->telegram_id, json_encode($coefficientDate));
                    // $this->notifyUser($user->telegram_id, json_encode($checkUntilDate));
                    $this->notifyUser($user->telegram_id, json_encode($coefficient));
                    // Get the cached coefficient value for the specific date
                    $cacheKey = 'coefficient_' . $coefficientDate->toDateString();
                    $lastCoefficientValue = Cache::get($cacheKey);

                    if ($trackedCoefficient !== null && $trackedCoefficient <= $coefficient['coefficient']) {
                        //Check if we already sent user info about changed coeff
                        if ($lastCoefficientValue === null || $lastCoefficientValue != $coefficient['coefficient']) {
                            $date = Carbon::parse($coefficient['date'])->locale('ru')->isoFormat('D MMMM');
                            $warehouseName = $coefficient['warehouseName'];
                            $boxTypeName = $coefficient['boxTypeName'];
                            $coeff = $coefficient['coefficient'];
                            // Coefficient has changed, notify the user and update the cache
                            $message = "🔔Найден тайм-слот\n
                            Дата: {$date}\n
                            Склад: {$warehouseName}\n
                            Короб: {$boxTypeName}\n
                            Стоимость приемки: x{$coeff}"
                            $this->notifyUser($user->telegram_id, $message);
                            Cache::put($cacheKey, $coefficient['coefficient'], $checkUntilDate);
                        }

                        $checkUntilDate = $settings['checkUntilDate'];
                        $checkUntilDate = Carbon::parse($checkUntilDate);
                        if($settings['date'] == 'untilfound' || Carbon::now()->greaterThan($checkUntilDate)){
                            $notification->status = 'finished';
                            $notification->save();
                        }
                    }
                }
            } else{
                break;
            }

        }
    }

    protected function hasCheckUntilDatePassed($checkUntilDate)
    {
        if ($checkUntilDate) {
            $checkUntilDate = Carbon::parse($checkUntilDate);
            return Carbon::now()->greaterThan($checkUntilDate);
        }
        return false;
    }
    protected function notifyUser($chatId, $message){
        $telegram = $this->useTelegram();
        $telegram->sendMessage($chatId, $message, 'HTML', false, null, null);
    }
}
