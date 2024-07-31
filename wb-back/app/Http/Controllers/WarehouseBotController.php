<?php

namespace App\Http\Controllers;

use TelegramBot\Api\Client;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Traits\UsesWildberriesSupplies;
use App\Jobs\DeleteTelegramMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseBotController extends Controller
{
    use UsesWildberriesSupplies;
    protected $bot;
    // Define constants for box types, coefficients, and dates
    const BOX_TYPES = [
        'korob' => '📦Короб',
        'monopalet' => '📦Монопаллет',
        'supersafe' => '📦Суперсейф'
    ];
    
    const COEFFICIENTS = [
        '0' => 'Бесплатная',
        '1' => 'До х1',
        '2' => 'До х2',
        '3' => 'До х3',
        '4' => 'До х4'
    ];
    
    const DATES = [
        'today' => 'Сегодня',
        'tomorrow' => 'Завтра',
        'week' => 'Неделя',
        'untilfound' => 'Искать пока не найдется',
        'customdates' => 'Ввести свою дату'
    ];

    public function __construct(Client $bot)
    {
        $this->bot = $bot;
    }
    

    protected function getGlobalButtons()
    {
        return [
            [['text' => '📦Склады', 'callback_data' => 'wh_warehouses']],
            [['text' => '🔎Поиск тайм-слотов', 'callback_data' => 'wh_notification']],
            [['text' => '🏠 Главное меню', 'callback_data' => 'wh_main_menu']]
        ];
    }

    protected function sendOrUpdateMessage($chatId, $messageId = null, $message, $keyboard = null, $parsemode = null){
        if ($messageId) {
            try {
                $this->bot->editMessageText($chatId, $messageId, $message, $parsemode, false, $keyboard);
            } catch (\Exception $e) {
                // If editing fails, send a new message
                $this->bot->sendMessage($chatId, $message, $parsemode, false, null, $keyboard);
            }
        } else {
            $this->bot->sendMessage($chatId, $message, $parsemode, false, null, $keyboard);
        }
    }

    public function handleStart($chatId, $messageId = null)
    {
        $message = "🤗 Данный бот помогает найти бесплатную приемку на вб.\n\n🆓 Мы даем тебе <b>2 недели бесплатного доступа</b>, а дальше по подписке.";
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '📦 Склады', 'callback_data' => 'wh_warehouses'], ['text' => '🔎Поиск тайм-слотов', 'callback_data' => 'wh_notification']],
            [['text' => '💵 Оплата', 'callback_data' => 'wh_payment']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function handleWarehouses($chatId, $page = 1, $messageId, $callbackData = 'wh_warehouse_get_')
    {
        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пользователь не найден');
            return;
        }
        $apiKey = $user->getSuppliesApiKey();
        if (!$apiKey) {
            $this->bot->sendMessage($chatId, 'Нет ключа авторизации для службы Supplies.');
            return;
        }

        $warehousesResponse = Cache::remember('warehouses', 6 * 60, function() use ($user, $apiKey)  {
            return $this->useWildberriesSupplies($apiKey)->getWarehouses();
        });
    
        if ($warehousesResponse['error']) {
            $this->bot->sendMessage($chatId, 'Произошла ошибка при получении данных складов: ' . $warehousesResponse['errorText']);
            return;
        }
    
        $warehouses = $warehousesResponse['data'];
        $totalWarehouses = count($warehouses);
        $perPage = 5;
        $totalPages = ceil($totalWarehouses / $perPage);
        $page = max(1, min($totalPages, $page));
        $start = ($page - 1) * $perPage;
        $currentWarehouses = array_slice($warehouses, $start, $perPage);
    
        $keyboardButtons = [];
    
        foreach ($currentWarehouses as $warehouse) {
            $keyboardButtons[] = [['text' => $warehouse['name'], 'callback_data' => $callbackData . $warehouse['ID']]];
        }
    
        $navigationButtons = [];
        $pageCallback = 'wh_warehouses_page_';
        if($callbackData == 'wh_warehouse_set_'){
            $pageCallback = 'wh_warehouses_set_page_'; 
        }
        if ($page > 1) {
            $navigationButtons[] = ['text' => '← Назад', 'callback_data' => $pageCallback . ($page - 1)];
        }
        if ($page < $totalPages) {
            $navigationButtons[] = ['text' => 'Вперед →', 'callback_data' => $pageCallback . ($page + 1)];
        }
        if (!empty($navigationButtons)) {
            $keyboardButtons[] = $navigationButtons;
        }
    
        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
    
        $message = '✅Выберите склад чтобы узнать коэффициенты:'; 
        
        if($callbackData == 'wh_warehouse_set_'){
            $message = '✅Выберите склад';
        }
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleNotification($chatId, $messageId)
    {
        $message = 'Поиск слотов - запуск отслеживания по вашим параметрам, без автоматического бронирования. Как только нужный слот будет найдет - вам придет уведомление.';
        $keyboard = new InlineKeyboardMarkup([
            [['text' => 'Приступить 🏁', 'callback_data' => 'wh_choose_warehouse']],
            [['text' => '← В главное меню', 'callback_data' => 'wh_main_menu']]
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handlePayment($chatId, $messageId, $step)
    {
        if($step == 'init'){
            $message = "Стоимость подписки 2000 рублей в месяц. Ваша подписка до 28 июля.";
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '💵Оплатить', 'callback_data' => 'wh_payment_success']],
                [['text' => '← В главное меню', 'callback_data' => 'wh_main_menu']]
            ]);
        }
        elseif($step == 'success'){
            $message = "Спасибо за оплату! Ваша подписка до 28 августа.";
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '← В главное меню', 'callback_data' => 'wh_main_menu']]
            ]);
        }

        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleInlineQuery($chatId, $data, $messageId = null)
    {
        if ($data === 'wh_warehouses') {
            $this->handleWarehouses($chatId, 1, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_warehouses_page_') === 0) {
            $page = (int)str_replace('wh_warehouses_page_', '', $data);
            $this->handleWarehouses($chatId, $page, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_warehouse_get_') === 0) {
            // Extract the warehouse ID and page number if present
            $data = str_replace('wh_warehouse_get_', '', $data);
            if (strpos($data, '_page_') !== false) {
                list($warehouseId, $page) = explode('_page_', $data);
                $this->handleWarehouseAction($chatId, (int)$warehouseId, (int)$page, $messageId);
            } else {
                $warehouseId = (int)$data;
                $this->handleWarehouseAction($chatId, $warehouseId, 1, $messageId);
            }
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'wh_choose_warehouse') {
            $this->handleWarehouses($chatId, 1, $messageId, 'wh_warehouse_set_');
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_warehouse_set_') === 0) {
            $warehouseId = str_replace('wh_warehouse_set_', '', $data);
            $this->handleBoxTypes($chatId, $warehouseId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_warehouses_set_page_') === 0) {
            $page = (int)str_replace('wh_warehouses_set_page_', '', $data);
            $this->handleWarehouses($chatId, $page, $messageId, 'wh_warehouse_set_');
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_box_type_set_') === 0) {
            list($warehouseId, $boxType) = explode('_', str_replace('wh_box_type_set_', '', $data), 2);
            $this->handleCoefficientChoice($chatId, $warehouseId, $boxType, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_coefficient_set_') === 0) {
            list($warehouseId, $boxType, $coefficient) = explode('_', str_replace('wh_coefficient_set_', '', $data), 3);
            $this->handleDateChoice($chatId, $warehouseId, $boxType, $coefficient, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_date_set_') === 0) {
            list($warehouseId, $boxType, $coefficient, $date) = explode('_', str_replace('wh_date_set_', '', $data), 4);
            $this->handleDateSelection($chatId, $warehouseId, $boxType, $coefficient, $date, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif (strpos($data, 'wh_start_notification_') === 0) {
            $notification_id = str_replace('wh_start_notification_', '', $data);
            $this->handleStartNotification($chatId, $messageId, $notification_id);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'wh_notification') {
            $this->handleNotification($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'wh_payment') {
            $this->handlePayment($chatId, $messageId, 'init');
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'wh_payment_success') {
            $this->handlePayment($chatId, $messageId, 'success');
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'wh_main_menu') {
            $this->handleStart($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } elseif ($data === 'wh_warehouse_bot') {
            $this->handleStart($chatId, $messageId);
            return response()->json(['status' => 'success'], 200);
        } else {
            return response()->json(['status' => 'success'], 200);
        }
    }
    
    // Update handleBoxTypes method
    public function handleBoxTypes($chatId, $warehouseId, $messageId)
    {
        $keyboardButtons = [];
    
        foreach (self::BOX_TYPES as $id => $boxType) {
            $keyboardButtons[] = [['text' => $boxType, 'callback_data' => 'wh_box_type_set_' . $warehouseId . '_' . $id]];
        }
    
        // Add main menu button on a new line
        $keyboardButtons[] = [['text' => '🏠 Главное меню', 'callback_data' => 'wh_main_menu']];
    
        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
    
        $message = 'Выберите тип коробки:';
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    // Update handleCoefficientChoice method
    public function handleCoefficientChoice($chatId, $warehouseId, $boxType, $messageId)
    {
        $keyboardButtons = [];

        foreach (self::COEFFICIENTS as $id => $coefficient) {
            $keyboardButtons[] = [['text' => $coefficient, 'callback_data' => 'wh_coefficient_set_' . $warehouseId . '_' . $boxType . '_' . $id]];
        }

        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
        $message = 'Выберите тип приемки, на который будем искать слот:';
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    // Update handleDateChoice method
    public function handleDateChoice($chatId, $warehouseId, $boxType, $coefficient, $messageId)
    {
        $keyboardButtons = [];

        foreach (self::DATES as $id => $date) {
            $keyboardButtons[] = [['text' => $date, 'callback_data' => 'wh_date_set_' . $warehouseId . '_' . $boxType . '_' . $coefficient . '_' . $id]];
        }

        // Add main menu button on a new line
        $keyboardButtons[] = [['text' => '🏠 Главное меню', 'callback_data' => 'wh_main_menu']];

        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
        $message = 'Выберите даты, когда вам нужны лимиты:';
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    // Update handleDateSelection method
    public function handleDateSelection($chatId, $warehouseId, $boxType, $coefficient, $date, $messageId)
    {
        $now = Carbon::now();
        $checkUntilDate = $now;
        $boxTypeId = 2;//Коробка
        switch ($date) {
            case 'today':
                $checkUntilDate = $now->endOfDay();
                break;
            case 'tomorrow':
                $checkUntilDate = $now->addDay()->endOfDay();
                break;
            case 'week':
                $checkUntilDate = $now->addWeek()->endOfDay();
                break;
            case 'untilfound':
                $checkUntilDate = now()->addYears(5); // No end date
                break;
            case 'customdates':
                // Temporarily set to null; will be updated when custom date is provided
                $checkUntilDate = now()->addYears(5);
                break;
        }

        switch($boxType){
            case 'korob':
               $boxTypeId = 2;
               break;
            case 'monopalet':
                $boxTypeId = 5;
                break;  
            case 'supersafe':
                $boxTypeId = 6;
                break;  
        }
        // Cache the notification settings
        $cacheKey = 'notification_settings_' . $chatId;
        $settings = [
            'type' => 'warehouse_bot',
            'chatId' => $chatId,
            'warehouseId' => $warehouseId,
            'boxType' => $boxType,
            'boxTypeId' => $boxTypeId,
            'coefficient' => $coefficient,
            'date' => $date,
            'checkUntilDate' => $checkUntilDate ? $checkUntilDate->toDateTimeString() : null,
        ];

        $user = User::where('telegram_id', $chatId)->first();
        $notification = Notification::create([
            'user_id' => $user->id,
            'settings' => $settings,
            'status' => 'not_started'
        ]);

        if ($date === 'customdates') {
            Cache::put("session_{$chatId}", ['action' => 'collect_notification_expiration_date', 'notification_id' => $notification->id], 300); // Cache for 5 minutes
            $this->bot->sendMessage($chatId, 'Введите дату в формате YYYY-MM-DD:');
        } else {
            $this->sendNotificationSummary($chatId, $notification, $messageId);
        }
    }

    public function handleCustomDateInput($chatId, $customDate)
    {
        // Validate the custom date format
        if (!Carbon::hasFormat($customDate, 'Y-m-d')) {
            $this->bot->sendMessage($chatId, 'Неверный формат даты. Пожалуйста, введите дату в формате YYYY-MM-DD.');
            return;
        }
    
        // Retrieve the session data from the cache
        $sessionData = Cache::get("session_{$chatId}", null);
        if (!$sessionData || $sessionData['action'] !== 'collect_notification_expiration_date') {
            $this->bot->sendMessage($chatId, 'Сессия истекла или неверное действие. Пожалуйста, начните заново.');
            return;
        }
    
        // Retrieve and update the notification
        $notification = Notification::find($sessionData['notification_id']);
        if ($notification) {
            $settings = $notification->settings;
            $settings['checkUntilDate'] = Carbon::parse($customDate)->endOfDay()->toDateTimeString();
            $notification->settings = $settings;
            $notification->save();
    
            // Remove the session data from the cache
            Cache::forget("session_{$chatId}");
    
            $this->sendNotificationSummary($chatId, $notification);
        } else {
            $this->bot->sendMessage($chatId, 'Не удалось найти уведомление. Пожалуйста, начните заново.');
        }
    }

    public function sendNotificationSummary($chatId, $notification, $messageId = null)
    {
        $settings = $notification->settings;
        // Retrieve warehouse name from cached warehouses
        $warehouses = Cache::get('warehouses', []);
        $warehouseName = $settings['warehouseId'];

        // Check if the 'data' key exists in the $warehouses array
        if (isset($warehouses['data']) && is_array($warehouses['data'])) {
            foreach ($warehouses['data'] as $warehouse) {
                if (isset($warehouse['ID']) && $warehouse['ID'] == $settings['warehouseId']) {
                    $warehouseName = $warehouse['name'];
                    break;
                }
            }
        }
        
        // Retrieve human-readable labels from constants
        $boxType = self::BOX_TYPES[$settings['boxType']] ?? 'Unknown';
        $coefficient = self::COEFFICIENTS[$settings['coefficient']] ?? 'Unknown';
        $date = self::DATES[$settings['date']] ?? 'Unknown';
        $checkUntilDate = $settings['checkUntilDate'] ?? 'Unknown';

        Log::info('settings', [$settings]);
        $message = "Ваши настройки уведомлений:\n";
        $message .= "Склад: {$warehouseName}\n";
        $message .= "Тип коробки: {$boxType}\n";
        $message .= "Тип приемки: {$coefficient}\n";
        $message .= "Проверять до: {$checkUntilDate}\n";
    
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '✅Запустить поиск', 'callback_data' => 'wh_start_notification_' . $notification->id]],
            [['text' => '🏠 Главное меню', 'callback_data' => 'wh_main_menu']]
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleStartNotification($chatId, $messageId, $notification_id)
    {
        $notification = Notification::find($notification_id);
        if ($notification) {
            $notification->status = 'started';
            $notification->save();
        } else {
            $this->bot->sendMessage($chatId, 'Не удалось найти уведомление. Пожалуйста, начните заново.');
            return;
        }
        $message = 'Мы уже ищем тайм-слот для вашей поставки!';
        
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '← В главное меню', 'callback_data' => 'wh_main_menu']]
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }


    public function handleWarehouseAction($chatId, $warehouseId, $page = 1, $messageId = null)
    {
        // Define cache key
        $cacheKey = 'acceptance_coefficients_' . $warehouseId;

        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пользователь не найден');
            return;
        }
        $apiKey = $user->getSuppliesApiKey();
        if (!$apiKey) {
            $this->bot->sendMessage($chatId, 'Нет ключа авторизации для службы Supplies.');
            return;
        }

        // Fetch the acceptance coefficients for the warehouse, with caching
        $coefficientsResponse = Cache::remember($cacheKey, 6 * 60, function() use ($warehouseId, $user, $apiKey) {
            return $this->useWildberriesSupplies($apiKey)->getAcceptanceCoefficients($warehouseId);
        });
    
        if ($coefficientsResponse['error']) {
            $this->bot->sendMessage($chatId, 'Произошла ошибка при получении коэффициентов: ' . $coefficientsResponse['errorText']);
            return;
        }
    
        // Check if the data is an array
        if (!is_array($coefficientsResponse['data'])) {
            $message = $this->bot->sendMessage($chatId, '😔Для данного склада нет коэффициентов');
            $errorMessageId = $message->getMessageId();
        

            DeleteTelegramMessage::dispatch($chatId, $errorMessageId); 

            return;
        }
    
        // Prepare the details message
        $coefficients = $coefficientsResponse['data'];
        $groupedCoefficients = [];
    
        // Group the coefficients by boxTypeName
        foreach ($coefficients as $coefficient) {
            $boxTypeName = $coefficient['boxTypeName'];
            $date = Carbon::parse($coefficient['date'])->locale('ru')->isoFormat('D MMMM');
            $coefficientValue = $coefficient['coefficient'];
            $warehouseName = $coefficient['warehouseName'];
    
            if (!isset($groupedCoefficients[$boxTypeName])) {
                $groupedCoefficients[$boxTypeName] = [];
            }
    
            $groupedCoefficients[$boxTypeName][] = [
                'date' => $date,
                'coefficient' => $coefficientValue,
            ];
        }
    
        // Pagination logic
        $groupedBoxTypes = array_keys($groupedCoefficients);
        $totalItems = count($groupedBoxTypes);
        $perPage = 1; // Show one box type per page
        $totalPages = ceil($totalItems / $perPage);
        $page = max(1, min($totalPages, $page));
        $start = ($page - 1) * $perPage;
        $currentBoxType = $groupedBoxTypes[$start];
    
        // Construct the message for the current page
        $message = "🏢 Склад: {$warehouseName}\n";
        $message .= "📦 Тип коробки: {$currentBoxType}\n\n📊 Коэффициенты:\n";
        foreach ($groupedCoefficients[$currentBoxType] as $entry) {
            $message .= "📅 {$entry['date']} — X{$entry['coefficient']}\n";
        }
        $message .= "\n--------------------------\n";
    
        // Create navigation buttons
        $keyboardButtons = [];
        if ($page > 1) {
            $keyboardButtons[] = ['text' => '← Назад', 'callback_data' => 'wh_warehouse_get_' . $warehouseId . '_page_' . ($page - 1)];
        }
        if ($page < $totalPages) {
            $keyboardButtons[] = ['text' => 'Вперед →', 'callback_data' => 'wh_warehouse_get_' . $warehouseId . '_page_' . ($page + 1)];
        }

        $keyboard = new InlineKeyboardMarkup(array_merge([$keyboardButtons], $this->getGlobalButtons()));
    
        // Send or update the message for the current page
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
        return response()->json(['status' => 'success'], 200);
    }
}
