<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'settings',
        'status'
    ];

    protected $casts = [
        'settings' => 'array', // Cast the settings column to an array
    ];
}