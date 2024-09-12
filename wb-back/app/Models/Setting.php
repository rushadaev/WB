<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class Setting extends Model
{
    use CrudTrait;

    protected $fillable = ['prompt', 'context', 'settings'];

    // Optionally, cast the 'settings' field as JSON
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Boot method to listen to model events.
     */
    protected static function booted()
    {
        static::updated(function ($setting) {
            // Call the artisan command 'feedback_send' when the model is updated
            Artisan::call('feedback_send');
        });
    }
}