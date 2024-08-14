<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\UsesWildberriesSupplies;
use App\Models\WarehouseCoefficient;

class FetchWarehouseCoefficientsJob implements ShouldQueue
{
    use Queueable;
    use UsesWildberriesSupplies;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiKey = config('wildberries.supplies_api_key');
        $coefficients = $this->useWildberriesSupplies($apiKey)->getAcceptanceCoefficients();

        // Delete records that have dates before today
        WarehouseCoefficient::where('date', '<', now()->format('Y-m-d'))->delete();
        
        // Save them to the database
        foreach ($coefficients['data'] as $coefficient) {
            WarehouseCoefficient::updateOrCreate(
                [
                    'warehouse_id' => $coefficient['warehouseID'],
                    'box_type_id' => $coefficient['boxTypeID'] ?? null,
                    'date' => $coefficient['date'],
                ],
                [
                    'warehouse_name' => $coefficient['warehouseName'],
                    'box_type_name' => $coefficient['boxTypeName'],
                    'coefficient' => $coefficient['coefficient'],
                ]
            );
        }
    }
}
