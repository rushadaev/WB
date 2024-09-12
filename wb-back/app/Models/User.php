<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;

class User extends Authenticatable
{
    use CrudTrait;
    use HasFactory, Notifiable;

    protected $dates = ['subscription_until'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telegram_id',
        'subscription_until',
        'is_paid',
        'tokens',
        'gifted',
        'gifted_2',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_until' => 'datetime',
        ];
    }
    public function apiKeys()
    {
        return $this->hasMany(APIKey::class);
    }

    // Define the relationship with Cabinet
    public function cabinets()
    {
        return $this->hasMany(Cabinet::class);
    }
 

    public function apiKeysCount(){
        return $this->apiKeys()->count();
    }
    
    public function getSuppliesApiKey()
    {
        $apiKey = $this->apiKeys()->where('service', 'supplies')->first();
        return $apiKey ? $apiKey->api_key : config('wildberries.supplies_api_key');
    }

    public function getFeedbackApiKey()
    {
        $apiKey = $this->apiKeys()->where('service', 'feedback')->first();
        return $apiKey ? $apiKey->api_key : null;
    }
    
    /**
     * Check if the user has an active subscription.
     *
     * @return bool
     */
    public function getHasActiveSubscriptionAttribute()
    {
        return $this->subscription_until && $this->subscription_until->isFuture();
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
