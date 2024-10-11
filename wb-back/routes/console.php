<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\TelegramInspire;
use App\Jobs\CheckBookingCoefficients;
use App\Jobs\CheckCoefficientChanges;
use App\Jobs\FetchWarehouseCoefficientsJob;
use App\Jobs\CheckSubscriptionExpiration;
use App\Jobs\SendFeedbacksToTelegramJob;
use App\Jobs\FetchFeedbacksJob;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use App\Jobs\SendTelegramMessage;
use App\Models\Cabinet;
use App\Models\Notification;
use App\Models\User;



Artisan::command('warehouse_bot', function () {
   CheckCoefficientChanges::dispatch(config('telegram.bot_token_supplies'));
})->purpose('Fetch coefficients and check for changes')->everyMinute();

Artisan::command('coefficients:booking', function () {
   CheckBookingCoefficients::dispatch(config('telegram.bot_token_supplies'));
})->purpose('Fetch booking coefficient from wb');

// Artisan::command('warehouse_bot', function () {
//       CheckCoefficientChanges::dispatch(config('telegram.bot_token_supplies_new'));
// })->purpose('Fetch coefficients and check for changes');


Artisan::command('warehouse_bot_check_subscription_expiration', function () {
   CheckSubscriptionExpiration::dispatch();
})->purpose('Check subscription expiration')->hourly();


Artisan::command('mailing:go', function () {
   $users = User::whereNotNull('telegram_id')->get();
   // $users = User::where('telegram_id', '782919745')->get();
   foreach ($users as $user) {
       $chatId = $user->telegram_id;
       $message = "🔥 Отличная новость, друзья!

Мы обновили нашего бота и теперь автобронирование поставок стало проще, чем когда-либо!

Что нового?
Мы добавили функцию «автоматического бронирования». Теперь вам не нужно тратить время на поиски и ручное бронирование — бот сделает всё за вас!

🚀 Каждому пользователю начислено 1 бесплатное автобронирование, чтобы вы могли протестировать новый сервис прямо сейчас.

📦 Наши преимущества:

— Самые низкие коэффициенты на бронирование.
— Поддержка неограниченного количества кабинетов.
— Коэффициенты обновляются в реальном времени каждую секунду. 
— Серьёзная экономия вашего времени и денег.
— Бесплатный поиск доступных тайм-слотов.

❗️Важно: 

Если в процессе автобронирования возникнут ошибки, пишите нам сюда: @dmitrynovikov21. 

За <b>каждую ошибку</b> мы подарим вам 1 дополнительное автобронирование.";
       $keyboard = new InlineKeyboardMarkup([
           [['text' => '👌 Начать', 'callback_data' => 'mainmenu']]
       ]);

       SendTelegramMessage::dispatch($chatId, $message, 'HTML', $keyboard, config('telegram.bot_token_supplies'));
   }
})->purpose('Mailing');
