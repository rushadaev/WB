<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cabinet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    // Define the relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Define the relationship with APIKey
    public function apiKeys()
    {
        return $this->hasMany(APIKey::class);
    }
}