<?php

namespace App\Traits;

use App\Services\WildberriesService;

trait UsesWildberries
{
    protected function useWildberries(): WildberriesService
    {
        return app(WildberriesService::class);
    }
}