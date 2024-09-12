<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cabinet;
use App\Http\Controllers\WelcomeBotController;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use App\Jobs\DeleteTelegramMessage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use TelegramBot\Api\Client;

class FeedbackOnboardingController extends Controller
{
    protected $bot;

    public function __construct(Client $bot)
    {
        $this->bot = $bot;
    }

    public function setupBrand($chatId, $messageId = null)
    {
        //Check if user already has a cabinet and brand name
        $user = Auth::user();
        $cabinet = $user->cabinets()->first();
        if (!$cabinet) {
            // Create a new cabinet with the provided name and default settings
            $cabinet = $user->cabinets()->create([
                'name' => 'Кабинет #' . $user->id,
                'settings' => [], // Default settings
            ]);
        }
        
        $this->setupMode($chatId, $messageId, $cabinet->id);
    }

    public function setupCabinet($chatId, $cabinetId, $messageIdOriginal)
    {
        $cabinet = Cabinet::findOrFail($cabinetId);
        $settings = $cabinet->settings;
        $mySettingsString = '';
        $modeHuman = [
            'auto_response' => 'Автоматический ответ',
            'positive_response' => 'Ответы на положительные',
            'manual_confirmation' => 'Ручное подтверждение',
            'combined' => 'Комбинированный режим',
        ];

        $mySettingsString .= "⚙️ Режим: <code>" . $modeHuman[$settings['onboarding']['mode']] . "</code>\n\n";
        $mySettingsString .= "💬 Рекламное сообщение: <code>" . $settings['onboarding']['advertisement_message'] . "</code>\n\n";
        $mySettingsString .= "🚀 Призыв к действию: <code>" . $settings['onboarding']['call_to_action'] . "</code>\n\n";
        
        $message = "Ваши текущие настройки:
        
{$mySettingsString}";
        
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '⚙️ Изменить режим', 'callback_data' => 'welcome_handle_mode_' . $cabinet->id]],
            [['text' => '⚙️ Изменить рекламное сообщение', 'callback_data' => 'welcome_setup_advertisement_message_' . $cabinet->id]],
            [['text' => '⚙️ Изменить призыв к действию', 'callback_data' => 'welcome_setup_call_to_action_' . $cabinet->id]],
            [['text' => '🏠 Вернуться в кабинет', 'callback_data' => 'welcome_cabinet']]
        ]);

        $this->sendOrUpdateMessage($chatId, $messageIdOriginal, $message, $keyboard, 'HTML');
    }

    public function setupMode($chatId, $messageIdOriginal, $cabinetId, $from = null)
    {
        $cabinet = Cabinet::findOrFail($cabinetId);
        //Current settings
        $settings = $cabinet->settings;
        // Current mode
        $mode = $settings['onboarding']['mode'] ?? null;
        
        Cache::forget("session_{$chatId}");
        $message = "Я могу обрабатывать ваши отзывы несколькими способами.
Выберите подходящий режим:

 1️⃣ <b>Комбинированный режим (Рекомендуется):</b> Я автоматически отвечу на положительные отзывы, а вы сможете вручную подтвердить ответы на негативные.

 2️⃣ <b>Автоматический ответ на все отзывы:</b> Я буду автоматически отвечать на все поступающие отзывы — быстро и без вашего участия.

 3️⃣ <b>Ответы только на положительные отзывы:</b> Я буду автоматически отвечать на отзывы с оценкой 4-5 звезд. Отзывы с низкой оценкой (1-3 звезды) отправлю вам для ручного ответа.

 4️⃣ <b>Ручное подтверждение:</b> Я сгенерирую ответ на каждый отзыв, а вы решите, отправить его, изменить или написать свой.

Какой режим подходит вам?

Я рекомендую подключить <b>Комбинированный режим</b>, чтобы иметь контроль над негативными отзывами и автоматизировать ответы на положительные.";

        $modeHuman = [
            'auto_response' => 'Автоматический ответ',
            'positive_response' => 'Ответы на положительные',
            'manual_confirmation' => 'Ручное подтверждение',
            'combined' => 'Комбинированный режим',
        ];
        
        if($mode){
            $message .= "\n\nТекущий режим: <code>{$modeHuman[$mode]}</code>";
        };
        
        //save $from in cache
        if($from)
            Cache::put("from_{$chatId}", $from, 300); // Cache for 5 minutes

        $keyboard = new InlineKeyboardMarkup([
            [['text' => '1️⃣ Комбинированный режим', 'callback_data' => 'welcome_set_mode_combined_'.$cabinet->id]],
            [['text' => '2️⃣ Автоматический ответ', 'callback_data' => 'welcome_set_mode_auto_response_'.$cabinet->id]],
            [['text' => '3️⃣ Ответы на положительные', 'callback_data' => 'welcome_set_mode_positive_response_'.$cabinet->id]],
            [['text' => '4️⃣ Ручное подтверждение', 'callback_data' => 'welcome_set_mode_manual_confirmation_'.$cabinet->id]],
        ]);
        $this->sendOrUpdateMessage($chatId, $messageIdOriginal, $message, $keyboard, 'HTML');
    }

    public function setMode($chatId, $mode_and_cabinet_id, $messageId)
    {
        $user = Auth::user();
    
        // Extract cabinetId from the string (last element after the last underscore)
        $parts = explode('_', $mode_and_cabinet_id);
        $cabinetId = array_pop($parts);
    
        // Recombine the remaining parts for the mode
        $mode = implode('_', $parts);
    
        // Find the cabinet for the authenticated user
        $cabinet = $user->cabinets()->findOrFail($cabinetId);
    
        // Retrieve the current settings
        $settings = $cabinet->settings;
    
        // Initialize onboarding settings if they don't exist
        $settings = $this->checkOnboarding($settings); 
    
        // Set the onboarding mode
        $settings['onboarding']['mode'] = $mode;
        $cabinet->settings = $settings;
        $cabinet->save();
    
        //get from from cache
        $from = Cache::get("from_{$chatId}");
        if($from == 'welcome_setup_cabinet'){
            $this->setupCabinet($chatId, $cabinetId, $messageId);
        } else {
            $this->sendSetAdvertisementMessage($chatId, $messageId, $cabinetId);
        }
        Cache::forget("from_{$chatId}");
       
    }
    
    public function setAdvertisementMessage($chatId, $text, $cabinetId, $messageId)
    {
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);
        $settings = $cabinet->settings;
        // Init onboarding settings if they don't exist
        $settings = $this->checkOnboarding($settings); 

        $settings['onboarding']['advertisement_message'] = $text;
        $cabinet->settings = $settings;
        $cabinet->save();

        //get from from cache
        $from = Cache::get("from_{$chatId}");
        if($from == 'welcome_setup_cabinet'){
            $this->setupCabinet($chatId, $cabinetId, $messageId);
        } else {
            $this->sendSetCallToAction($chatId, $messageId, $cabinetId);
        }
        Cache::forget("from_{$chatId}");
    }

    public function setCallToAction($chatId, $text, $cabinetId, $messageId)
    {
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);
        $settings = $cabinet->settings;
        // Init onboarding settings if they don't exist
        $settings = $this->checkOnboarding($settings); 

        $settings['onboarding']['call_to_action'] = $text;
        $cabinet->settings = $settings;
        $cabinet->save();
        
        $isKeyExists = $cabinet->getFeedbackApiKey();

        //get from from cache
        $from = Cache::get("from_{$chatId}");
        if($from == 'welcome_setup_cabinet'){
            $this->setupCabinet($chatId, $cabinetId, $messageId);
        } else {
            if($isKeyExists){
                $this->listSettings($chatId, $messageId, $cabinetId);
            } else {
                $this->sendFinishOnboarding($chatId, $messageId, $cabinetId);
            }
        }
        Cache::forget("from_{$chatId}");
    }
    
    public function sendSetAdvertisementMessage($chatId, $messageId = null, $cabinetId, $from = null)
    {
        //Check if message already set
        $cabinet = Cabinet::findOrFail($cabinetId);
        $settings = $cabinet->settings;
        $message = '';
        // Init onboarding settings if they don't exist
        if($settings['onboarding']['advertisement_message']){
            $message = "Ваше рекламное сообщение: 
<code>{$settings['onboarding']['advertisement_message']}</code>

Если вы хотите изменить рекламное сообщение, то отправьте его мне👇";
        } else {
        $message = "Теперь давай настроим рекламное сообщение.
 
В ответы на отзывы я могу добавлять рекламу ваших новых товаров. Сообщение будет добавляться в каждый ответ на отзыв.
Введите рекламное сообщение по примеру ниже:

<code>Также у нас появился новый ароматизатор для дома (АРТИКУЛ 778856476)</code>

Отправьте сообщение или пропустите этот шаг 👇";
        }

        //save $from in cache
        if($from)
            Cache::put("from_{$chatId}", $from, 300);
        
        //Set cache session to collection advertisement message
        Cache::put("session_{$chatId}", ['action' => 'collect_advertisement_message','cabinet_id' => $cabinetId, 'messageId' => $messageId], 300); // Cache for 5 minutes
        $keyboard = new InlineKeyboardMarkup([
            [['text' => 'Пропустить ➡️', 'callback_data' => 'welcome_skip_advertisement_message_'. $cabinetId]] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function sendSetCallToAction($chatId, $messageId = null, $cabinetId, $from = null)
    {
        $cabinet = Cabinet::findOrFail($cabinetId);
        //check if mode already set
        $settings = $cabinet->settings;
        $message = '';
        if($settings['onboarding']['call_to_action']){
            $message = "Ваш призыв к действию для добавления бренда в избранное: 
<code>{$settings['onboarding']['call_to_action']}</code>

Если вы хотите изменить призыв, то отправьте его мне👇";
        } else {
            $message = "Напишите призыв к действию для добавления бренда в избранное.

Пример: <code>Добавьте бренд LOCAL в избранное, чтобы не пропустить новинки: нажав на бренд, а потом на 'сердечко' или кнопку '+нравится'</code>

Отправьте сообщение или пропустите этот шаг 👇
";
        }
        
        //save $from in cache
        if($from)
            Cache::put("from_{$chatId}", $from, 300); // Cache for 5 minutes

        //Set cache session to collection call to action
        Cache::put("session_{$chatId}", ['action' => 'collect_call_to_action','cabinet_id' => $cabinetId, 'messageId' => $messageId], 300); // Cache for 5 minutes
        $keyboard = new InlineKeyboardMarkup([
            [['text' => 'Пропустить ➡️', 'callback_data' => 'welcome_skip_call_to_action_' . $cabinetId]] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }
    public function skipAdvertisementMessage($chatId, $cabinetId, $messageId){
        $from = Cache::get("from_{$chatId}");
        if($from == 'welcome_setup_cabinet'){
            $this->setupCabinet($chatId, $cabinetId, $messageId);
        } else {
            $this->sendSetCallToAction($chatId, $messageId, $cabinetId);
        }
        Cache::forget("from_{$chatId}");
        Cache::forget("session_{$chatId}");
    }
    public function skipCallToAction($chatId, $cabinetId, $messageId){
        //check if key exists in cabinet
        $cabinet = Cabinet::findOrFail($cabinetId);
        $isKeyExists = $cabinet->getFeedbackApiKey();

        $from = Cache::get("from_{$chatId}");
        if($from == 'welcome_setup_cabinet'){
            $this->setupCabinet($chatId, $cabinetId, $messageId);
        } else {
            if($isKeyExists){
                $this->listSettings($chatId, $messageId, $cabinetId);
            } else {
                $this->sendFinishOnboarding($chatId, $messageId, $cabinetId);
            }
        }
        Cache::forget("from_{$chatId}");
        Cache::forget("session_{$chatId}");
    }

    public function listSettings($chatId, $messageId = null, $cabinetId)
    {
        $cabinet = Cabinet::findOrFail($cabinetId);
        $settings = $cabinet->settings;
        $mySettingsString = '';
        $modeHuman = [
            'auto_response' => 'Автоматический ответ',
            'positive_response' => 'Ответы на положительные',
            'manual_confirmation' => 'Ручное подтверждение',
            'combined' => 'Комбинированный режим',
        ];

        $mySettingsString .= "Режим: " . $modeHuman[$settings['onboarding']['mode']] . "\n\n";
        $mySettingsString .= "Рекламное сообщение: <code>" . $settings['onboarding']['advertisement_message'] . "</code>\n\n";
        $mySettingsString .= "Призыв к действию: <code>" . $settings['onboarding']['call_to_action'] . "</code>\n\n";
        $mySettingsString .= "Бренд: " . $cabinet->name . "\n";

        $message = "Ваши текущие настройки:

{$mySettingsString}"
;
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🏠 Вернуться в кабинет', 'callback_data' => 'welcome_cabinet']] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function sendFinishOnboarding($chatId, $messageId = null, $cabinetId)
    {
        $mySettingsString = '';
        $settings = Cabinet::findOrFail($cabinetId)->settings;

        $modeHuman = [
            'auto_response' => 'Автоматический ответ',
            'positive_response' => 'Ответы на положительные',
            'manual_confirmation' => 'Ручное подтверждение',
            'combined' => 'Комбинированный режим',
        ];

        $mySettingsString .= "Режим: <code>" . $modeHuman[$settings['onboarding']['mode']] . "</code>\n";
        $mySettingsString .= "Рекламное сообщение: <code>" . $settings['onboarding']['advertisement_message'] . "</code>\n";
        $mySettingsString .= "Призыв к действию: <code>" . $settings['onboarding']['call_to_action'] . "</code>\n";

        $message = "Поздравляю, вы настроили меня для генерации ответов на отзывы.
Мои настройки:

{$mySettingsString}

В профиле вы всегда можете изменить эти настройки на другие 🙌
";
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🚀 Активировать меня', 'callback_data' => 'welcome_add_key']] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function setStartMode($chatId, $cabinetId, $messageId, $mode)
    {
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);
        $settings = $cabinet->settings;
        // Init onboarding settings if they don't exist
        $settings = $this->checkOnboarding($settings); 

        $settings['onboarding']['start_mode'] = $mode;
        $cabinet->settings = $settings;
        $cabinet->save();
        
        $this->sendResultMessage($chatId, $messageId, $cabinetId, $mode);
    }

    public function sendResultMessage($chatId, $messageId = null, $cabinetId, $mode)
    {
        //Mode is new or all
        if($mode == 'new'){
            $message = "Отлично!

С этого момента я буду автоматически отвечать на все новые отзывы, поступающие в ваш кабинет 🚀";
        }else{
            $message = "Отлично, приступаю!

Я сейчас займусь ответами на все неотвеченные отзывы в вашем кабинете. Это займет немного времени, и вскоре все ваши клиенты получат ответы.";
        }
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function checkOnboarding($settings)
    {
        if (!isset($settings['onboarding']) || !is_array($settings['onboarding'])) {
            $settings['onboarding'] = [
                'brand_name' => null,
                'mode' => null,
                'advertisement_message' => null,
                'call_to_action' => null,
                'start_mode' => null,
            ];
        }
        return $settings;
    }

    protected function sendOrUpdateMessage($chatId, $messageId = null, $message, $keyboard = null, $parse_mode = null)
    {
        if ($messageId) {
            try {
                return $this->bot->editMessageText($chatId, $messageId, $message, $parse_mode, false, $keyboard);
            } catch (\Exception $e) {
                // If editing fails, send a new message
                return $this->bot->sendMessage($chatId, $message, $parse_mode, false, null, $keyboard);
            }
        } else {
            return $this->bot->sendMessage($chatId, $message, $parse_mode, false, null, $keyboard);
        }
    }
}