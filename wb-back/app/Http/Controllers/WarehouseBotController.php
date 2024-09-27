<?php

namespace App\Http\Controllers;

use App\Traits\UsesNodeApiService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rules\In;
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
use App\Models\WarehouseCoefficient;
use App\Jobs\SendUserNotificationMessage;
use Illuminate\Support\Facades\DB;

class WarehouseBotController extends Controller
{
    use UsesWildberriesSupplies;
    use UsesNodeApiService;
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

    const WAREHOUSES = [
        218987 => "Алматы Атакент",
        204939 => "Астана",
        324108 => "Астана 2",
        206236 => "Белые Столбы",
        301983 => "Волгоград",
        317470 => "Голицыно СГТ",
        300461 => "Гомель 2",
        208941 => "Домодедово",
        1733 => "Екатеринбург - Испытателей 14г",
        300571 => "Екатеринбург - Перспективный 12/2",
        117986 => "Казань",
        206844 => "Калининград",
        303295 => "Клин",
        507 => "Коледино",
        301809 => "Котовск",
        130744 => "Краснодар (Тихорецкая)",
        6145 => "Красноярск",
        211622 => "Минск",
        208277 => "Невинномысск",
        301805 => "Новосемейкино",
        686 => "Новосибирск",
        218210 => "Обухово",
        312617 => "Обухово СГТ",
        106476 => "Оренбург",
        117501 => "Подольск",
        218623 => "Подольск 3",
        301229 => "Подольск 4",
        300169 => "Радумля СГТ",
        301760 => "Рязань (Тюшевское)",
        206298 => "СЦ Абакан",
        300862 => "СЦ Абакан 2",
        316879 => "СЦ Актобе",
        214951 => "СЦ Артем",
        209207 => "СЦ Архангельск",
        302769 => "СЦ Архангельск (ул Ленина)",
        169872 => "СЦ Астрахань",
        302988 => "СЦ Астрахань (Солянка)",
        215020 => "СЦ Байсерке",
        302737 => "СЦ Барнаул",
        172430 => "СЦ Барнаул old",
        210557 => "СЦ Белогорск",
        216476 => "СЦ Бишкек",
        300363 => "СЦ Брест",
        172940 => "СЦ Брянск",
        302856 => "СЦ Видное",
        158751 => "СЦ Владикавказ",
        144649 => "СЦ Владимир",
        210127 => "СЦ Внуково",
        301516 => "СЦ Волгоград 2",
        6144 => "СЦ Волгоград old",
        203631 => "СЦ Вологда",
        300219 => "СЦ Вологда 2",
        211415 => "СЦ Воронеж",
        210515 => "СЦ Вёшки",
        211644 => "СЦ Екатеринбург 2 (Альпинистов)",
        218402 => "СЦ Иваново",
        203632 => "СЦ Иваново (до 03.05.23)",
        218628 => "СЦ Ижевск",
        158140 => "СЦ Ижевск (до 29.05)",
        131643 => "СЦ Иркутск",
        117442 => "СЦ Калуга",
        213849 => "СЦ Кемерово",
        303219 => "СЦ Киров",
        205205 => "СЦ Киров (old)",
        154371 => "СЦ Комсомольская",
        6159 => "СЦ Красногорск",
        205985 => "СЦ Крыловская",
        302335 => "СЦ Кузнецк",
        140302 => "СЦ Курск",
        156814 => "СЦ Курьяновская",
        160030 => "СЦ Липецк",
        117289 => "СЦ Лобня",
        313214 => "СЦ Магнитогорск",
        209211 => "СЦ Махачкала",
        117393 => "СЦ Минск",
        121700 => "СЦ Минск 2",
        205349 => "СЦ Мурманск",
        204952 => "СЦ Набережные Челны",
        118535 => "СЦ Нижний Новгород",
        211470 => "СЦ Нижний Тагил",
        141637 => "СЦ Новокосино",
        206708 => "СЦ Новокузнецк",
        161520 => "СЦ Новосибирск Пасечная",
        303221 => "СЦ Ноябрьск",
        312807 => "СЦ Обухово 2",
        168458 => "СЦ Омск",
        206319 => "СЦ Оренбург",
        315199 => "СЦ Оренбург Центральная",
        218732 => "СЦ Ош",
        216566 => "СЦ Пермь 2",
        208647 => "СЦ Печатники",
        124716 => "СЦ Подрезково",
        209209 => "СЦ Псков",
        207743 => "СЦ Пушкино",
        158311 => "СЦ Пятигорск",
        301920 => "СЦ Пятигорск (Этока)",
        300168 => "СЦ Радумля",
        218616 => "СЦ Ростов-на-Дону",
        118019 => "СЦ Ростов-на-Дону old-1",
        133533 => "СЦ Ростов-на-Дону old-2",
        6156 => "СЦ Рязань",
        117230 => "СЦ Самара",
        158929 => "СЦ Саратов",
        303189 => "СЦ Семей",
        169537 => "СЦ Серов",
        144154 => "СЦ Симферополь",
        210937 => "СЦ Симферополь 2",
        207803 => "СЦ Смоленск 2",
        300987 => "СЦ Смоленск 3",
        209596 => "СЦ Солнцево",
        161003 => "СЦ Сургут",
        209208 => "СЦ Сыктывкар",
        117866 => "СЦ Тамбов",
        218636 => "СЦ Ташкент",
        117456 => "СЦ Тверь",
        204615 => "СЦ Томск",
        117819 => "СЦ Тюмень",
        205104 => "СЦ Ульяновск",
        300711 => "СЦ Уральск",
        149445 => "СЦ Уфа",
        218644 => "СЦ Хабаровск",
        203799 => "СЦ Чебоксары",
        218916 => "СЦ Чебоксары 2",
        132508 => "СЦ Челябинск",
        218225 => "СЦ Челябинск 2",
        311895 => "СЦ Череповец",
        218674 => "СЦ Чита 2",
        207022 => "СЦ Чёрная Грязь",
        312259 => "СЦ Шушары",
        218698 => "СЦ Шымкент",
        158328 => "СЦ Южные Ворота",
        207404 => "СЦ Ярославль",
        2737 => "Санкт-Петербург (Уткина Заводь)",
        159402 => "Санкт-Петербург (Шушары)",
        1680 => "Саратов Депутатская РЦ",
        122259 => "Склад поставщика КБТ 96 ч",
        217081 => "Сц Брянск 2",
        302445 => "Сынково",
        206348 => "Тула",
        303024 => "Улан-Удэ, Ботаническая",
        302222 => "Уфа, Зубово",
        1193 => "Хабаровск",
        321932 => "Чашниково",
        206968 => "Чехов 1, Новоселки вл 11 стр 2",
        210001 => "Чехов 2, Новоселки вл 11 стр 7",
        300864 => "Шелепаново",
        120762 => "Электросталь"
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

    public function handleSearches($chatId, $messageId = null)
    {
        $user = User::where('telegram_id', $chatId)->first();

        // Fetch all notifications for the user
        $notifications = Notification::where('user_id', $user->id)->get();

        // Initialize the message components
        $messages = [];
        $currentMessage = "Ваши поиски:\n\n";
        $maxMessageLength = 4096; // Telegram message length limit
        $currentMessageLength = strlen($currentMessage);

        // Load the warehouses list from the configuration file
        $warehouses = config('warehouses.list');

        foreach ($notifications as $notification) {
            $settings = $notification->settings; // Directly use the settings array

            $warehouseId = (int)$settings['warehouseId'];
            $warehouseName = $warehouses[$warehouseId] ?? "Склад {$warehouseId}";

            // Determine the status emoji based on the notification status
            $statusEmoji = match($notification->status) {
                'not_started' => '🔴',
                'started' => '🟡',
                'expired' => '️💤',
                'finished' => '🟢',
                default => '❓', // Fallback emoji for any unknown status
            };
            $statusDescription = match($notification->status) {
                'not_started' => 'Отменен',
                'started' => 'Ищем тайм-слот',
                'expired' => 'Истек',
                'finished' => 'Тайм-слот найден',
                default => '❓', // Fallback emoji for any unknown status
            };


            // Format the notification details with emojis
            $formattedMessage = "{$statusEmoji} Склад: {$warehouseName}\n";
            $formattedMessage .= "⏰ Время: " . ($settings['checkUntilDate'] ?? 'Не указано') . "\n";
            $formattedMessage .= "💰 Коэффициент: " . ($settings['coefficient'] == '0' ? 'Бесплатная' : $settings['coefficient']) . "\n";
            $formattedMessage .= "📋 Статус: " . $statusDescription . "\n\n";

            // Check if adding this formatted message would exceed the limit
            if ($currentMessageLength + strlen($formattedMessage) > $maxMessageLength) {
                // Save the current message to the list and start a new one
                $messages[] = $currentMessage;
                $currentMessage = "Ваши поиски (продолжение):\n\n";
                $currentMessageLength = strlen($currentMessage);
            }

            // Append the formatted message to the current message
            $currentMessage .= $formattedMessage;
            $currentMessageLength += strlen($formattedMessage);
        }

        // Add the last message to the list
        if (!empty(trim($currentMessage))) {
            $messages[] = $currentMessage;
        }

        $keyboard = new InlineKeyboardMarkup([
            [['text' => '📦 Узнать КФ', 'callback_data' => 'wh_warehouses'], ['text' => '🔎 Найти тайм-слот', 'callback_data' => 'wh_notification']],
            [['text' => '🏠 На главную', 'callback_data' => 'wh_main_menu']],
        ]);

        // Send or update messages with the keyboard
        foreach ($messages as $index => $msg) {
            if ($index === 0 && $messageId) {
                // Update the first message if $messageId is provided
                $this->sendOrUpdateMessage($chatId, $messageId, $msg, $keyboard, 'HTML');
            } else {
                // Send subsequent messages as new messages
                $this->sendOrUpdateMessage($chatId, null, $msg, $keyboard, 'HTML');
            }
        }
    }

    public function handleStart($chatId, $messageId = null)
    {
        $user = User::where('telegram_id', $chatId)->first();
        $subscription_until = $user->subscription_until;
        $isPaid = $user->is_paid;
        if($subscription_until){
            if (Carbon::parse($subscription_until)->year >= 2124) {
                $formattedDate = 'Ваша подписка действует навсегда';
            } else {
                $formattedDate = 'Ваша подписка действует до ' . Carbon::parse($user->subscription_until)->format('d-m-Y');
            }
        }
        if(!$isPaid){
            $formattedDate = 'У вас действует 3 дня бесплатного доступа🤝';
        }

        if (!$user->has_active_subscription) {
            $message = "Найдите бесплатную приемку на WB 🔥

Мы помогаем отслеживать доступные бесплатные приемки на Wildberries. Вы можете проверить текущий коэффициент онлайн или настроить уведомления о доступных бесплатных слотах для приемки товара. 🤙

Как это работает?

1. Выберите склад.
2. Укажите в чем будете отгружать.
3. Выберите тип приемки.
4. Ждите уведомления.

Как только появится подходящий тайм-слот, мы сразу же отправим вам уведомление. Вы можете ставить любое количество уведомлений

⚠️Подписка закончилась, необходимо оплатить";
        }
        else {
        $message = "Найдите бесплатную приемку на WB 🔥

Мы помогаем отслеживать доступные бесплатные приемки на Wildberries. Вы можете проверить текущий коэффициент онлайн или настроить уведомления о доступных бесплатных слотах для приемки товара. 🤙

Как это работает?

1. Выберите склад.
2. Укажите в чем будете отгружать.
3. Выберите тип приемки.
4. Ждите уведомления.

Как только появится подходящий тайм-слот, мы сразу же отправим вам уведомление. Вы можете ставить любое количество уведомлений

{$formattedDate}
";
        }
//        $keyboard = new InlineKeyboardMarkup([
//            [['text' => '📦 Узнать КФ', 'callback_data' => 'wh_warehouses'], ['text' => '🔎 Найти тайм-слот', 'callback_data' => 'wh_notification']],
//            [['text' => '💵 Подписка', 'callback_data' => 'wh_payment']]
//        ]);

        $keyboard = new InlineKeyboardMarkup([
                [
                    ['text' => '📦 Автобронирование', 'callback_data' => 'wh_notification']
                ],
                [
                    ['text' => '⚡ Поиск слотов', 'callback_data' => 'wh_notification'],
                    ['text' => '📝 Заявки на поиск слотов', 'callback_data' => 'wh_notification']
                ],
                [
                    ['text' => '🙌 Мои кабинеты', 'callback_data' => 'wh_payment'],
                    ['text' => '💎 Подписка', 'callback_data' => 'wh_payment']
                ],
                [
                    ['text' => '💬 Поддержка', 'url' => 'https://t.me/dmitrynovikov21'],
                    ['text' => '📍 Инструкции', 'url' => 'https://t.me/dmitrynovikov21']
                ]
            ]
        );


        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    /**
     * Extend the user's subscription by a given number of days.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function extend(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        $user->subscription_until = now()->addDays($request->days);
        $user->save();

    }

    public function handleWarehouses($chatId, $page = 1, $messageId, $callbackData = 'wh_warehouse_get_')
    {
        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пользователь не найден');
            return;
        }

        if (!$user->has_active_subscription) {
            $this->handlePayment($chatId, $messageId, 'init');
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
        // Define the prioritized warehouses in the desired order
        $prioritizedWarehouses = [
            'Коледино' => 507,
            'Электросталь' => 120762,
            'Подольск' => 117501,
            'Подольск 3' => 218623,
            'Подольск 4' => 301229,
            'Кузнецк' => 302335,
            'Казань' => 117986,
            'Краснодар (Тихорецкая)' => 130744,
            'Тула' => 206348,
            'Белые Столбы' => 206236,
            'Невинномысск' => 208277,
            'Екатеринбург - Испытателей 14г' => 1733,
            'Екатеринбург - Перспективный 12/2' => 300571,
            'Новосибирск' => 686,
            'Чашниково' => 321932,
            'Рязань (Тюшевское)' => 301760,
        ];

        // Separate and sort prioritized warehouses
        $prioritizedList = [];
        $otherWarehouses = [];

        foreach ($prioritizedWarehouses as $name => $id) {
            foreach ($warehouses as $warehouse) {
                if ($warehouse['ID'] == $id && $warehouse['name'] == $name) {
                    $prioritizedList[] = $warehouse;
                    break;
                }
            }
        }

        foreach ($warehouses as $warehouse) {
            if (!in_array($warehouse, $prioritizedList)) {
                $otherWarehouses[] = $warehouse;
            }
        }

        // Merge prioritized warehouses with the rest
        $warehouses = array_merge($prioritizedList, $otherWarehouses);

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
        $user = User::where('telegram_id', $chatId)->first();

        if (!$user->has_active_subscription) {
            $this->handlePayment($chatId, $messageId, 'init');
            return;
        }

        $message = 'Поиск слотов - запуск отслеживания по вашим параметрам, без автоматического бронирования. Как только нужный слот будет найдет - вам придет уведомление.';
        $keyboard = new InlineKeyboardMarkup([
            [['text' => 'Приступить 🏁', 'callback_data' => 'wh_choose_warehouse']],
            [['text' => '← В главное меню', 'callback_data' => 'wh_main_menu']]
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handlePayment($chatId, $messageId, $step)
    {
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '1 неделя -> 300р', 'callback_data' => 'pay_1_week']],
            [['text' => '1 месяц -> 500р', 'callback_data' => 'pay_1_month']],
            [['text' => '3 месяца -> 1000р', 'callback_data' => 'pay_3_months']],
            [['text' => '6 месяцев -> 4000р', 'callback_data' => 'pay_6_months']],
            [['text' => 'навсегда -> 5000р', 'callback_data' => 'pay_forever']],
            [['text' => '🏠 На главную', 'callback_data' => 'wh_main_menu']]
        ]);

        if($step == 'init'){
            $message = "Выберите тариф, чтобы продолжить";
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

        $username = $notification->user->name;
        $message = "#таймслот\n@{$username} поставил поиск тайм-слота на\nСклад: {$warehouseName}\nВремя: {$checkUntilDate}\nКоэффициент: {$coefficient}";
        SendUserNotificationMessage::dispatch($message, 'HTML');
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
        $coefficientsResponse = $this->useWildberriesSupplies($apiKey)->getStoredAcceptanceCoefficients($warehouseId);

        if ($coefficientsResponse['error']) {
            $this->bot->sendMessage($chatId, 'Произошла ошибка при получении коэффициентов: ' . $coefficientsResponse['errorText']);
            return;
        }

        // Check if the data is an array
        if (!is_array($coefficientsResponse['data'])) {
            $message = $this->bot->sendMessage($chatId, '😔Для данного склада нет коэффициентов');
            $errorMessageId = $message->getMessageId();


            DeleteTelegramMessage::dispatch($chatId, $errorMessageId, config('telegram.bot_token_supplies'));

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
            if ($entry['coefficient'] == '-1') {
                $message .= "🗓️ {$entry['date']} — временно недоступно\n";
            } else {
                $message .= "📆 {$entry['date']} — X{$entry['coefficient']}\n";
            }
        }

        $message .= "\n--------------------------\n\n Чтобы изменить тип коробки — нажмите «Вперед»";

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

    public function handleVerificationCode($chatId, $code){
        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пользователь не найден');
            return;
        }

        // Store the verification code in cache
        Cache::put("verification_code_{$user->telegram_id}", ['action' => 'collect_verification_code', 'code' => $code], 300);

        Redis::connection()->publish("verification_code_channel_{$chatId}", json_encode([
            'telegramId' => $user->telegram_id,
            'action' => 'collect_verification_code',
            'code' => $code,
        ]));

        Cache::forget("session_{$user->telegram_id}");
    }

    public function startAuth(mixed $chatId): void
    {
        Cache::put("session_{$chatId}", ['action' => 'collect_phone_number'], 300); // Cache for 5 minutes
        $this->bot->sendMessage($chatId, 'Пожалуйста, введите ваш номер телефона без 7 в формате 9991234567:');
    }

    public function handlePhoneNumber($chatId, $phoneNumber): void
    {
        //filter phone number in format 10 digits without 7
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        //if phone less than 10 digits return error
        if (strlen($phoneNumber) < 10) {
            $this->bot->sendMessage($chatId, 'Номер телефона должен содержать 10 цифр');
            return;
        }
        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пользователь не найден');
            return;
        }

        $user->phone_number = $phoneNumber;
        $user->save();

        $this->sendOrUpdateMessage($chatId, null, 'Запускаем авторизацию.', null);
        $this->useNodeApi()->authenticate($user, $phoneNumber);


    }

    public function handleDrafts(mixed $chatId)
    {
        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пользователь не найден');
            return;
        }


        try {
            $drafts = $this->useNodeApi()->listDrafts($user->id);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return;
        }


        $drafts = $drafts['data'] ?? [];

        if (empty($drafts)) {
            $this->bot->sendMessage($chatId, 'У вас нет черновиков');
            return;
        }


        $message = 'Ваши черновики:';
        $keyboardButtons = [];
        foreach ($drafts as $draft) {
            //$date format 02.12.24
            $date = Carbon::parse($draft['createdAt'])->format('d.m.y');
            $goodQuantity = $draft['goodQuantity'];
            $title = "{$date} – кол-во товаров – {$goodQuantity} шт.";
            $keyboardButtons[] = [['text' => $title, 'callback_data' => 'wh_draft_get_' . $draft['draftId']]];
        }

        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
        $this->sendOrUpdateMessage($chatId, null, $message, $keyboard);
    }
}
