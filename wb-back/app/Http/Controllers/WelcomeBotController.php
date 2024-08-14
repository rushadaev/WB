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
use App\Models\Cabinet;
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
    
    protected function sendOrUpdateMessage($chatId, $messageId = null, $message, $keyboard = null, $parse_mode = null){
        if ($messageId) {
            try {
                $this->bot->editMessageText($chatId, $messageId, $message, $parse_mode, false, $keyboard);
            } catch (\Exception $e) {
                // If editing fails, send a new message
                $this->bot->sendMessage($chatId, $message, $parse_mode, false, null, $keyboard);
            }
        } else {
            $this->bot->sendMessage($chatId, $message, $parse_mode, false, null, $keyboard);
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

· ID: {$user->telegram_id}
· Кабинетов: {$keysCount}";

        // Default keyboard buttons
        $keyboardButtons = [
            [['text' => '🔑 Ключи', 'callback_data' => 'welcome_cabinet_list'], ['text' => '💳 Оплата', 'callback_data' => 'welcome_pay']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']]
        ];

        // Conditionally add the "Setup cabinet" button if the user has API keys
        if ($keysCount > 0) {
            array_unshift($keyboardButtons, [['text' => '🔧 Настроить кабинет', 'callback_data' => 'welcome_setup_cabinet']]);
        }

        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
        
    
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

    public function handleManageCabinet($chatId, $messageId = null)
    {
        $user = Auth::user();

        // Step 1: Retrieve the first (and only) cabinet for the user
        $cabinet = $user->cabinets()->firstOrFail();
        $cabinetId = $cabinet->id;
        // Step 2: Prepare the message content with cabinet details
        $message = "🆔 {$cabinet->id}\n📋: {$cabinet->name}";

        // Step 3: Create an inline keyboard for managing the cabinet
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '⚙️ Настроить отзывы', 'callback_data' => "welcome_manage_reviews_{$cabinetId}"]],
            [['text' => '⚙️ Настроить вопросы', 'callback_data' => "welcome_manage_questions_{$cabinetId}"]],
            [['text' => '❌ Удалить кабинет', 'callback_data' => "welcome_delete_cabinet_{$cabinetId}"]],
            [['text' => '🔙 Назад', 'callback_data' => 'welcome_cabinet']]
        ]);

        // Step 4: Send or update the message with the cabinet management options
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }
    
    public function handleInlineQuery($chatId, $data, $messageId = null)
    {
        if ($data === 'welcome_start') {
            $this->handleStart($chatId, $messageId);
        } elseif ($data === 'welcome_advertisement') {
            $this->handleAdvertisement($chatId, $messageId);
        } elseif ($data === 'welcome_cabinet') {
            $this->handleCabinet($chatId, $messageId);
        } elseif ($data === 'welcome_pay') {
            $this->handlePay($chatId, $messageId);
        } elseif ($data === 'welcome_cabinet_list') {
            $this->handleCabinetList($chatId, $messageId);
        } elseif ($data === 'welcome_add_key') {
            $this->handleAddKey($chatId, $messageId);
        } elseif ($data === 'welcome_setup_cabinet'){
            $this->handleManageCabinet($chatId, $messageId);
        } elseif (strpos($data, 'welcome_manage_reviews_') === 0) {
            $cabinetId = str_replace('welcome_manage_reviews_', '', $data);
            $this->handleManageReviews($chatId, $cabinetId, $messageId);
        } elseif (strpos($data, 'welcome_manage_questions_') === 0) {
            $cabinetId = str_replace('welcome_manage_questions_', '', $data);
            $this->handleManageQuestions($chatId, $cabinetId, $messageId);
        } elseif (strpos($data, 'welcome_delete_cabinet_') === 0) {
            $cabinetId = str_replace('welcome_delete_cabinet_', '', $data);
            $this->handleDeleteCabinet($chatId, $cabinetId, $messageId);
        } else {
            return response()->json(['status' => 'success'], 200);
        }
    
        return response()->json(['status' => 'success'], 200);
    }

    //Настройки отзывов
    protected function handleManageReviews($chatId, $cabinetId, $messageId = null)
    {
        // Retrieve the cabinet
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);

        // Generate a unique command for adding the bot to the chat
        $uniqueCommand = 'AddReviews_' . $cabinetId;

        // Instructions message
        $message = "Чтобы включить автоматические ответы, нужно подключить чат.
1️⃣ Создайте чат
2️⃣ Нажмите на кнопку снизу и выберите нужный чат
3️⃣ Если бот просит ввести команду, отправьте в чат <code>/start $uniqueCommand</code> (нажмите для копирования)";

        $botUsername = 'wbhelpyfb_bot';
        $link = "https://t.me/{$botUsername}?startgroup=true";
        // Inline keyboard with a button to switch inline chat, allowing group chat selection
        $keyboard = new InlineKeyboardMarkup([
            [[
                'text' => '+ Добавить чат',
                'url' => $link 
            ]],
            [['text' => '🔙 Назад', 'callback_data' => 'welcome_setup_cabinet']]
        ]);

        // Send or update the message with the instructions and options
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    protected function handleManageQuestions($chatId, $cabinetId, $messageId = null)
    {
        // Retrieve the cabinet
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);

        // Placeholder message for managing questions
        $message = "Вы находитесь в разделе управления вопросами для кабинета: {$cabinet->name}.

    Эта функция еще не реализована.";

        // Inline keyboard to return to cabinet management or main menu
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🔙 Назад к кабинету', 'callback_data' => 'welcome_cabinet']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']]
        ]);

        // Send or update the message
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }


    protected function handleDeleteCabinet($chatId, $cabinetId, $messageId = null)
    {
        $user = Auth::user();

        // Retrieve and delete the cabinet
        $cabinet = $user->cabinets()->findOrFail($cabinetId);
        $cabinet->delete();

        // Notify the user that the cabinet was deleted
        $message = "Кабинет '{$cabinet->name}' успешно удален.";

        // Inline keyboard to return to the main menu
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']]
        ]);

        // Send or update the message
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function setupCabinet($cabinetId, $chatId, $bot)
    {
        // Fetch the cabinet by ID
        $cabinet = Cabinet::findOrFail($cabinetId);

        // Decode the settings JSON into an associative array
        $settings = json_decode($cabinet->settings, true);

        if (!is_array($settings)) {
            $settings = []; // Initialize as an empty array if decoding fails or settings aren't an array
        }

        // Merge the existing settings with the new group_chat_id
        $settings = array_merge($settings, ['group_chat_id' => $chatId]);

        // Encode the updated settings back into JSON format before saving
        $cabinet->settings = json_encode($settings);

        // Save the updated cabinet settings
        $cabinet->save();

        // Log the event
        Log::info("Cabinet setup completed for chat: {$chatId}, cabinet ID: {$cabinetId}");

        // Send a welcome message to the chat
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🍓 Перейти в бота', 'url' => 'https://t.me/wbhelpyfb_bot']]
        ]);

        $message = "✅ Успешно подключено";
        $bot->sendMessage($chatId, $message, null, false, null, $keyboard);
    }
}
