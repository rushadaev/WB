<?php

namespace App\Http\Controllers;

use TelegramBot\Api\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use App\Traits\UsesWildberriesSupplies;
use App\Jobs\DeleteTelegramMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WelcomeBotController extends Controller
{
    use UsesWildberriesSupplies;
    protected $bot;

    public function __construct(Client $bot)
    {
        $this->bot = $bot;
    }
    
    protected function sendOrUpdateMessage($chatId, $messageId = null, $message, $keyboard = null){
        if ($messageId) {
            try {
                $this->bot->editMessageText($chatId, $messageId, $message, null, false, $keyboard);
            } catch (\Exception $e) {
                // If editing fails, send a new message
                $this->bot->sendMessage($chatId, $message, null, false, null, $keyboard);
            }
        } else {
            $this->bot->sendMessage($chatId, $message, null, false, null, $keyboard);
        }
    }

    public function handleStart($chatId, $messageId = null)
    {
        $message = "🎉 Добро пожаловать!\n

🍓 ✧ WB - Автоответ ✧ - умный помощник для вашего бизнеса.\n

🤖 В бота внедрён искусственный интеллект, который обучен максимально эффективно обрабатывать отзывы покупателей.\n

👤 Создает индивидуальный ответ, учитывая конкретные пожелания или проблемы клиента.\n

✨ Ответы совершенно не отличается от ответов человека!\n

👉 Бот поддерживает Wildberries\n

✅ Нам доверяют более 100 крупных поставщиков!";
        $keyboard = new InlineKeyboardMarkup([
            [['text' => 'Продолжить ➡️', 'callback_data' => 'welcome_advertisement']],
            [['text' => '👤 Перейти в кабинет ', 'callback_data' => 'welcome_cabinet']],
            [['text' => '📦 Бот поставок', 'callback_data' => 'wh_warehouse_bot']],
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleAdvertisement($chatId, $messageId = null)
    {
        $message = "🤔 Почему Вам стоит подключить бота?

Отзывы серьезно влияют на решение клиента при выборе товара. Клиенты намного положительнее относятся к продавцу и его продукту, если продавец активно работает с отзывами.

Но как успевать отвечать, делать каждый ответ уникальным и при этом избежать путаницы в указываемых рекомендациях?

И хотя шаблонные ответы справляются с частью  задач, их основной недостаток - отсутствие индивидуальности.

🍓 WB - Автоответ все это умеет!

🔄 Анализирует не только количество звезд в отзыве, но и текст комментария.

🛍 Уместно рекомендует товары, создавая связанные и интересные предложения.

⚙️ Может сразу отправлять ответы или только после Вашего подтверждения.

🖋 Умеет обращаться по имени, добавлять подпись к каждому ответу, благодарить за фото и многое другое!";
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '👤 Перейти в кабинет', 'callback_data' => 'welcome_cabinet']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleCabinet($chatId, $messageId = null)
    {
        $user = Auth::user();
        $keysCount = $user->apiKeysCount();
        $message = "🍓 Личный кабинет

· ID: {$chatId}
· Кабинетов: {$keysCount}";
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🔑 Ключи', 'callback_data' => 'welcome_cabinet_list'], ['text' => '💳 Оплата', 'callback_data' => 'welcome_pay']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handlePay($chatId, $messageId = null)
    {
        $user = Auth::user();
        $keysCount = $user->apiKeysCount();
        $message = "💳 Баланс: 0 токенов

· 1 токен = 1 генерация ответа
· Токены будут расходоваться на все подключенные кабинеты, не сгорают.

✅ Можно оплатить по счету через поддержку. Отправьте ИНН компании и необходимое кол-во токенов.

ℹ️ Оплачивая любой пакет, вы подверждаете согласие с офертой.";
    $keyboard = new InlineKeyboardMarkup([
        [['text' => '100 токенов -> 390р', 'callback_data' => 'pay_100_tokens']],
        [['text' => '500 токенов -> 1490р', 'callback_data' => 'pay_500_tokens']],
        [['text' => '1000 токенов -> 2290р', 'callback_data' => 'pay_1000_tokens']],
        [['text' => '5000 токенов -> 8490р', 'callback_data' => 'pay_5000_tokens']],
        [['text' => '10000 токенов -> 12990р', 'callback_data' => 'pay_10000_tokens']],
        [['text' => '💳 Оплата по счету', 'url' => 'https://your-payment-url.com']],
        [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']]
    ]);
        
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleCabinetList($chatId, $messageId = null)
    {
        $user = Auth::user();
        $apiKeys = $user->apiKeys;
    
        $message = "📝 Список подключенных ключей:\n\n";
    
        if ($apiKeys->isEmpty()) {
            $message .= "Нет подключенных ключей.\n";
        } else {
            foreach ($apiKeys as $apiKey) {
                $shortApiKey = '...' . substr($apiKey->api_key, -4); // Display only the last 4 characters
                $message .= "🛠️ Сервис: {$apiKey->service}\n🔑 Ключ: {$shortApiKey}\n\n";
            }
        }
    
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '+ Добавить ключ', 'callback_data' => 'welcome_add_key']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleAddKey($chatId, $messageId = null)
    {
        $user = Auth::user();
        $apiKeys = $user->apiKeys;
    
        $message = "🍓 Подключите кабинет по токену (его может получить только владелец магазина).

1️⃣ Зайдите в Личный кабинет WB -> Настройки -> Доступ к API (ссылка https://seller.wildberries.ru/supplier-settings/access-to-api).

2️⃣ Нажмите кнопку [+ Создать новый токен] и введите любое имя токена (например WbAutoReplyBot).

3️⃣ Выберите тип \"Вопросы и отзывы\".

4️⃣ Нажмите [Создать токен] и отправте его в этот чат.";
        if (!Gate::forUser($user)->allows('accessService', 'feedback')) {
            Cache::put("session_{$user->telegram_id}", ['action' => 'collect_wb_feedback_api_key'], 300); // Cache for 5 minutes
            $this->sendOrUpdateMessage($chatId, $messageId, $message, null);
            return false;
        }
        $message = '✅ У вас уже имеется ключ отзывы WB';
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '👤 Обратно в кабинет', 'callback_data' => 'welcome_cabinet']] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }
    
    public function handleInlineQuery($chatId, $data, $messageId = null)
    {
        if ($data === 'welcome_start') {
            $this->handleStart($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'welcome_advertisement') {
            $this->handleAdvertisement($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'welcome_cabinet') {
            $this->handleCabinet($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'welcome_pay') {
            $this->handlePay($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'welcome_cabinet_list') {
            $this->handleCabinetList($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'welcome_add_key') {
            $this->handleAddKey($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } else {
            return response()->json(['status' => 'success'], 200);
        }
    }
}
