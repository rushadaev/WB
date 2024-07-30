<?php

namespace App\Services;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class UserAuthorizationService
{
    public function isAllowFeedbackFunctions(User $user)
    {
        if (!Gate::forUser($user)->allows('accessService', 'feedback')) {
            Cache::put("session_{$user->telegram_id}", ['action' => 'collect_wb_feedback_api_key'], 300); // Cache for 5 minutes
            NotificationService::notify($user->telegram_id, '🗝️ Пожалуйста, предоставьте ваш API-ключ WB "Отзывы"');
            return false;
        }
        return true;
    }
}