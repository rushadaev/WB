<?php

namespace App\Http\Controllers;

use TelegramBot\Api\Client;
use App\Models\User;
use App\Http\Controllers\FeedbackAutoSendController;
use App\Http\Controllers\FeedbackConfirmController;
use App\Http\Controllers\FeedbackOnboardingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use App\Traits\UsesWildberriesSupplies;
use App\Traits\UsesWildberries;
use App\Jobs\DeleteTelegramMessage;
use App\Models\Cabinet;
use Carbon\Carbon;
use App\Models\Feedback;
use App\Jobs\GenerateChatGptResponseJob;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class WelcomeBotController extends Controller
{
    use UsesWildberriesSupplies;
    use UsesWildberries;
    
    public function __construct(
        protected Client $bot,
    ) {}
    
    protected function sendOrUpdateMessage($chatId, $messageId = null, $message, $keyboard = null, $parse_mode = null){
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

    public function handleStart($chatId, $messageId = null)
    {

        Cache::forget("session_{$chatId}");
        Cache::forget("add_key_message_id_{$chatId}");

        $message = "🎉 Добро пожаловать в умного помощника для вашего бизнеса!

Привет! Я helpy bot — ваш супер помощник для автоматизации ответов на отзывы покупателей на Wildberries и Ozon. 

Я помогу вам сэкономить время и улучшить связь с вашими клиентами, автоматизируя работу с отзывами.

Я работаю на базе chatGPT и генерирую уникальные, персонализированные ответы учитывая конкретные пожелания или проблемы клиента

Наша миссия 🤞
Мы верим, что селлеры должны тратить свое время на развитие и масштабирование бизнеса, а не на рутинные задачи. 

Именно поэтому я, helpy bot, беру на себя всю рутину, чтобы вы могли сосредоточиться на главном — росте вашего бизнеса.";
        
        $user = Auth::user();
        $cabinet = $user->cabinets()->exists();
        if(!$cabinet){
            $keyboard = new InlineKeyboardMarkup([
                [['text' => 'Продолжить ➡️', 'callback_data' => 'welcome_advertisement']],
            ]);
        }
        else{
            $this->handleCabinet($chatId, $messageId);
            return;
            $keyboard = new InlineKeyboardMarkup([
                [['text' => 'Продолжить ➡️', 'callback_data' => 'welcome_advertisement']],
                [['text' => '👤 Перейти в кабинет ', 'callback_data' => 'welcome_cabinet']],
            ]);
        }
    
        Cache::forget("session_{$chatId}");
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleAdvertisement($chatId, $messageId = null)
    {
        $message = "🤔 Почему вам стоит подключить helpy bot?

Ваши клиенты принимают решения о покупке, основываясь на отзывах. Продавец, который активно и качественно взаимодействует с отзывами, вызывает больше доверия и лояльности.

Но как обеспечить индивидуальный подход к каждому отзыву, не теряя времени?
Шаблонные ответы могут помочь, но они лишены той индивидуальности, которая важна для вашего бренда.

Что я умею?

— Я создаю уникальные и персонализированные ответы на отзывы ваших клиентов.
— Вы можете выбрать, какие отзывы я буду обрабатывать автоматически, а какие передам вам на подтверждение.
— Я умею встраивать рекламные сообщения в ответы на отзывы, чтобы помочь вам увеличить продажи.
— Я анализирую не только кол-во звезд в отзыве, но и сам отзыв.

Почему я?

— Я быстро и безопастно интегрируюсь с вашими кабинетами WB.
— Вы можете настраивать меня так, как вам удобно.
— Моя “служба поддержки” всегда на связи, готова помочь вам настроить и использовать мои возможности на 100%.

💼 Мне доверяют уже более 150 успешных продавцов, которые с моей помощью улучшили свою репутацию и увеличили продажи.

👉 Давайте начнем прямо сейчас! Я дарю вам 50 бесплатных ответов на отзывы — попробуйте все мои возможности и убедитесь в их эффективности ❤️";
        $user = Auth::user();
        $cabinet = $user->cabinets()->first();
        if(!$user->gifted){
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '🎁 Получить подарок', 'callback_data' => 'welcome_gift']],
                [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
            ]);
        } elseif(!$cabinet){
            $keyboard = new InlineKeyboardMarkup([
                [['text' => 'Далее ➡️', 'callback_data' => 'welcome_start_onboarding']],
                [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
            ]);
        }else{
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '👤 Перейти в кабинет ', 'callback_data' => 'welcome_cabinet']],
                [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
            ]);
        }
        
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function handleGift($chatId, $messageId = null)
    {
        
        $user = Auth::user();
        $message = "";
        if(!$user->gifted){
            $user->update(['tokens' => $user->tokens + 50, 'gifted' => true]);
            $message = "Я добавил вам на баланс 50 ответов🎁";
        }
        
        //We need to add only once so we will ad a column in the user table to check if the user has already received the gift
        $message .= "
А теперь давайте настроим режим ответов на отзывы 👇

Настройка займет всего пару минут, и это максимально просто, а в конце я подарю вам еще 20 бесплатных ответов 🤞";
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🏁 Начать', 'callback_data' => 'welcome_start_onboarding']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']] 
        ]);
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function handleOnboarding($chatId, $messageId = null)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setupBrand($chatId, $messageId);
    }

    public function handleCabinet($chatId, $messageId = null)
    {

        Cache::forget("session_{$chatId}");
        Cache::forget("add_key_message_id_{$chatId}");
        
        $user = Auth::user();
        $keysCount = $user->apiKeysCount();
        $cabinet = $user->cabinets()->first();
        $tokens = $user->tokens;
        if(!$cabinet){
            $this->handleStart($chatId, $messageId);
            return;
        }
        $feedbacksCount = Feedback::where('cabinet_id', $cabinet->id)->count() ?? 0;
        $message = "🍓 Личный кабинет

· ID: {$user->telegram_id}
· Ответов осталось: {$tokens}
· Обработано отзывов: {$feedbacksCount}

";

        // Default keyboard buttons
        $keyboardButtons = [
            [['text' => '🔑 Ключи', 'callback_data' => 'welcome_cabinet_list'], ['text' => '💳 Оплата', 'callback_data' => 'welcome_pay']],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']]
        ];

        // Conditionally add the "Setup cabinet" button if the user has API keys
        if ($keysCount > 0) {
            array_unshift($keyboardButtons, [['text' => '🔧 Настроить кабинет', 'callback_data' => 'welcome_manage_cabinet']]);
        }

        $keyboard = new InlineKeyboardMarkup($keyboardButtons);
        
    
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handlePay($chatId, $messageId = null)
    {
        $user = Auth::user();
        $keysCount = $user->apiKeysCount();
        $message = "💳 Баланс: 0 отзывов

· 1 токен = 1 генерация ответа
· Токены будут расходоваться на все подключенные кабинеты, не сгорают.

✅ Можно оплатить по счету через поддержку. Отправьте ИНН компании и необходимое кол-во отзывов.

ℹ️ Оплачивая любой пакет, вы подверждаете согласие с офертой.";
    $keyboard = new InlineKeyboardMarkup([
        [['text' => '100 отзывов -> 390р', 'callback_data' => 'pay_100_tokens']],
        [['text' => '500 отзывов -> 1490р', 'callback_data' => 'pay_500_tokens']],
        [['text' => '1000 отзывов -> 2290р', 'callback_data' => 'pay_1000_tokens']],
        [['text' => '5000 отзывов -> 8490р', 'callback_data' => 'pay_5000_tokens']],
        [['text' => '10000 отзывов -> 12990р', 'callback_data' => 'pay_10000_tokens']],
        [['text' => '🏠 Обратно в кабинет', 'callback_data' => 'welcome_cabinet']]
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
    
        $url = '<a href="https://seller.wildberries.ru/supplier-settings/access-to-api">тут</a>';
        $message = "Введите свой API ключ WB, его можно найти {$url}

1️⃣ Перейдите в личный кабинет WB → Настройки → Доступ к API

2️⃣ Нажмите кнопку «Создать новый токен» и введите название API ключа (например helpybot).

3️⃣ Выберите тип доступа «Вопросы и отзывы».

4️⃣ Нажмите «Создать токен» и отправьте его мне.

Не переживайте, я не имею доступа к вашим личным или финансовым данным. Я только получаю информацию по отзывам.";
        if (!Gate::forUser($user)->allows('accessService', 'feedback')) {
            $updatedMessage = $this->sendOrUpdateMessage($chatId, $messageId, $message, null, 'HTML');
            Cache::put("session_{$user->telegram_id}", ['action' => 'collect_wb_feedback_api_key', 'messageId' => $updatedMessage->getMessageId()], 300); // Cache for 5 minutes
            return false;
        }
        $message = '✅ У вас уже имеется ключ отзывы WB';
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '👤 Обратно в кабинет', 'callback_data' => 'welcome_cabinet']] 
        ]);
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
    }

    public function handleCongratulations($chatId, $cabinetId, $messageId = null)
    {
        $cabinet = Cabinet::find($cabinetId);
        $user = $cabinet->user;

        $apiKey = $cabinet->getFeedbackApiKey();
        $feedbacksCount = $this->useWildberries($apiKey, $user)->getCountUnansweredFeedbacks();
        //['data']['countUnanswered']
        $unansweredFeedbacks = $feedbacksCount['data']['countUnanswered'] ?? 0;

        $message = "";
        if(!$user->gifted_2){
            $user->update(['tokens' => $user->tokens + 20, 'gifted_2' => true]);
            $message = "Я добавил вам на баланс 20 ответов🎁\n\n";
        }
        $message .= "Поздравляю, настройка завершена!

Я обнаружил, что у вас есть {$unansweredFeedbacks} отзывов в вашем кабинете.

У вас есть два варианта:

	1.	Ответить на текущие неотвеченные отзывы: Я могу сразу же сгенерировать и отправить ответы на 50 из этих отзывов.
	2.	Начать работу с новыми отзывами: Я буду автоматически создавать и отправлять ответы на все новые отзывы, которые поступят.

Как вы хотите поступить? Выберите наиболее подходящий вариант, и я начну работать!";

        $keyboard = new InlineKeyboardMarkup([
            [['text' => "🚀 Ответить на {$unansweredFeedbacks} неотвеченных", 'callback_data' => 'welcome_set_start_mode_all_' . $cabinetId]],
            [['text' => '📝 Начать с новых отзывов', 'callback_data' => 'welcome_set_start_mode_new_' . $cabinetId]],
            [['text' => '🏠 На главную', 'callback_data' => 'welcome_start']]
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
            // [['text' => '🛠️ Общие настройки', 'callback_data' => "welcome_start_onboarding"]],
            [['text' => '⚙️ Настройки', 'callback_data' => "welcome_setup_cabinet_{$cabinetId}"]],
            [['text' => '❌ Удалить кабинет', 'callback_data' => "welcome_delete_cabinet_{$cabinetId}"]],
            [['text' => '🔙 Назад', 'callback_data' => 'welcome_cabinet']]
        ]);

        // Step 4: Send or update the message with the cabinet management options
        $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard);
    }

    public function handleSetupCabinet($chatId, $cabinetId, $messageId = null)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setupCabinet($chatId, $cabinetId, $messageId);
    }
    
    public function handleInlineQuery($chatId, $data, $messageId = null)
    {
        $mapping = [
            'welcome_start' => 'handleStart',
            'welcome_advertisement' => 'handleAdvertisement',
            'welcome_cabinet' => 'handleCabinet',
            'welcome_pay' => 'handlePay',
            'welcome_cabinet_list' => 'handleCabinetList',
            'welcome_add_key' => 'handleAddKey',
            'welcome_manage_cabinet' => 'handleManageCabinet',
            'welcome_gift' => 'handleGift',
            'welcome_start_onboarding' => 'handleOnboarding',
        ];
        switch (true) {
            case isset($mapping[$data]):
                $this->{$mapping[$data]}($chatId, $messageId);
                break;
            case strpos($data, 'welcome_setup_cabinet_') === 0:
                $cabinetId = str_replace('welcome_setup_cabinet_', '', $data);
                $this->handleSetupCabinet($chatId, $cabinetId, $messageId);
                break;
            case strpos($data, 'welcome_manage_reviews_') === 0:
                $cabinetId = str_replace('welcome_manage_reviews_', '', $data);
                $this->handleManageReviews($chatId, $cabinetId, $messageId);
                break;
        
            case strpos($data, 'welcome_manage_questions_') === 0:
                $cabinetId = str_replace('welcome_manage_questions_', '', $data);
                $this->handleManageQuestions($chatId, $cabinetId, $messageId);
                break;
        
            case strpos($data, 'welcome_delete_cabinet_') === 0:
                $cabinetId = str_replace('welcome_delete_cabinet_', '', $data);
                $this->handleDeleteCabinet($chatId, $cabinetId, $messageId);
                break;
            
            // change_answer_{$question->id}
            case strpos($data, 'change_answer_') === 0:
                $questionId = str_replace('change_answer_', '', $data);
                Log::info("Change answer for question ID: {$questionId}");
                $this->handleChangeAnswer($chatId, $questionId, $messageId);
                break;

            case strpos($data, 'welcome_feedback_settings_autosend_') === 0:
                $cabinetId = preg_replace('/^welcome_feedback_settings_autosend_/', '', $data);
                $cabinetId = (int) filter_var($cabinetId, FILTER_SANITIZE_NUMBER_INT);
                if (strpos($data, 'setup_') !== false) {
                    $this->handleAutosendSetup($chatId, $cabinetId, 'setup', $messageId);
                } elseif (strpos($data, 'toggle_') !== false) {
                    $this->handleAutosendToggle($chatId, $cabinetId, 'toggle', $messageId);
                } elseif (strpos($data, 'send_if_no_text_') !== false) {
                    $this->handleAutosendToggleNoText($chatId, $cabinetId, 'toggle_no_text', $messageId);
                } elseif (strpos($data, 'send_if_with_text_') !== false) {
                    $this->handleAutosendToggleWithText($chatId, $cabinetId, 'toggle_with_text', $messageId);
                }
                break;

            case strpos($data, 'welcome_feedback_settings_confirm_') === 0:
                $cabinetId = preg_replace('/^welcome_feedback_settings_confirm_/', '', $data);
                $cabinetId = (int) filter_var($cabinetId, FILTER_SANITIZE_NUMBER_INT);
                if (strpos($data, 'setup_') !== false) {
                    $this->handleConfirmSetup($chatId, $cabinetId, 'setup', $messageId);
                } elseif (strpos($data, 'toggle_') !== false) {
                    $this->handleConfirmToggle($chatId, $cabinetId, 'toggle', $messageId);
                } elseif (strpos($data, 'send_if_no_text_') !== false) {
                    $this->handleConfirmToggleNoText($chatId, $cabinetId, 'toggle_no_text', $messageId);
                } elseif (strpos($data, 'send_if_with_text_') !== false) {
                    $this->handleConfirmToggleWithText($chatId, $cabinetId, 'toggle_with_text', $messageId);
                }
                break;
        
            case strpos($data, 'welcome_feedback_settings_confirm_') === 0:
            case strpos($data, 'welcome_feedback_settings_recommend_') === 0:
            case strpos($data, 'welcome_feedback_settings_enabled_') === 0:
                $cabinetId = str_replace(['welcome_feedback_settings_confirm_', 'welcome_feedback_settings_recommend_', 'welcome_feedback_settings_enabled_'], '', $data);
                $setting = str_contains($data, 'confirm') ? 'confirm_before_sending' : (str_contains($data, 'recommend') ? 'recommend_products' : 'enabled');
                $this->handleToggleSetup($chatId, $cabinetId, $setting, $messageId);
                break;
            case strpos($data, 'welcome_handle_mode_') === 0:
                $cabinetId = str_replace('welcome_handle_mode_', '', $data);
                $this->handleModeSetup($chatId, $cabinetId, $messageId);
                break;
            case strpos($data, 'welcome_set_mode_') === 0:
                $mode_and_cabinet_id = str_replace('welcome_set_mode_', '', $data);
                $this->handleSetMode($chatId, $mode_and_cabinet_id, $messageId);
                break;
            case strpos($data, 'welcome_skip_advertisement_message_') === 0:
                $cabinetId = str_replace('welcome_skip_advertisement_message_', '', $data);
                $this->handleSkipAdvertisementMessage($chatId, $cabinetId, $messageId);
                break;
            case strpos($data, 'welcome_skip_call_to_action_') === 0:
                $cabinetId = str_replace('welcome_skip_call_to_action_', '', $data);
                $this->handleSkipCallToAction($chatId, $cabinetId, $messageId);
                break;    
            case strpos($data, 'welcome_set_start_mode_all_') === 0:
                $cabinetId = str_replace('welcome_set_start_mode_all_', '', $data);
                $this->handleSetStartMode($chatId, $cabinetId, $messageId, 'all');
                break;
            case strpos($data, 'welcome_set_start_mode_new_') === 0:
                $cabinetId = str_replace('welcome_set_start_mode_new_', '', $data);
                $this->handleSetStartMode($chatId, $cabinetId, $messageId, 'new');
                break;
            case strpos($data, 'welcome_add_group_') === 0:
                $cabinetId = str_replace('welcome_add_group_', '', $data);
                $this->handleAddGroup($chatId, $cabinetId, $messageId);
                break;
            
            case strpos($data, 'welcome_setup_advertisement_message_') === 0:
                $cabinetId = str_replace('welcome_setup_advertisement_message_', '', $data);
                $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
                $feedbackOnboardingController->sendSetAdvertisementMessage($chatId, $messageId, $cabinetId, 'welcome_setup_cabinet');
                break;

            case strpos($data, 'welcome_setup_call_to_action_') === 0:
                $cabinetId = str_replace('welcome_setup_call_to_action_', '', $data);
                $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
                $feedbackOnboardingController->sendSetCallToAction($chatId, $messageId, $cabinetId, 'welcome_setup_cabinet');
                break;
        
            default:
                return response()->json(['status' => 'success'], 200);
        }
    
        return response()->json(['status' => 'success'], 200);
    }

    public function handleModeSetup($chatId, $cabinetId, $messageId = null)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setupMode($chatId, $messageId, $cabinetId, 'welcome_setup_cabinet');
    }

    public function handleSetMode($chatId, $mode_and_cabinet_id, $messageId = null)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setMode($chatId, $mode_and_cabinet_id, $messageId);
    }

    public function handleSetStartMode($chatId, $cabinetId, $messageId = null, $mode)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setStartMode($chatId, $cabinetId, $messageId, $mode);
    }

    public function handleCollectAdvertisementMessage($chatId, $text, $cabinetId, $messageIdOriginal, $messageId)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setAdvertisementMessage($chatId, $text, $cabinetId, $messageIdOriginal, $messageId);
    }

    public function handleCollectCallToAction($chatId, $text, $cabinetId, $messageIdOriginal, $messageId)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->setCallToAction($chatId, $text, $cabinetId, $messageIdOriginal, $messageId);
    }

    public function handleSkipAdvertisementMessage($chatId, $cabinetId, $messageId = null)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->skipAdvertisementMessage($chatId, $cabinetId, $messageId);
    }
    public function handleSkipCallToAction($chatId, $cabinetId, $messageId = null)
    {
        $feedbackOnboardingController = new FeedbackOnboardingController($this->bot);
        $feedbackOnboardingController->skipCallToAction($chatId, $cabinetId, $messageId);
    }

    //Настройки отзывов
    public function handleManageReviews($chatId, $cabinetId, $messageId = null)
    {
        // Retrieve the cabinet
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);

        // Generate a unique command for adding the bot to the chat
        $uniqueCommand = 'AddReviews_' . $cabinetId;

        // Decode the settings JSON into an associative array
        $settings = $cabinet->settings;

        // Check if 'group_chat_id' exists and show relevant options
        if (isset($settings['group_chat_id'])) {
            $message = "⚙️ Настройка отзывов для {$cabinet->name}\n\n";
            $message .= "- Подтверждение перед отправкой: " . (($settings['confirm_before_sending'] ?? false) ? '<code>Включено</code>' : '<code>Отключено</code>') . "\n";
            $message .= "- Автоматическая отправка: " . (($settings['autosend']['enabled'] ?? false) ? '<code>Включено</code>' : '<code>Отключено</code>') . "\n";
            $message .= "- Рекомендация товаров: " . (($settings['recommend_products'] ?? false) ? '<code>Включено</code>' : '<code>Отключено</code>') . "\n\n";

            $message .= "- Работа бота: " . (($settings['enabled'] ?? false) ? '<code>Включена</code>' : '<code>Отключена</code>') . "\n";

            $keyboard = [];

            if ($settings['enabled'] ?? false) {
                // If the bot is enabled, show all options
                $keyboard = new InlineKeyboardMarkup([
                    [['text' => ($settings['confirm_before_sending']['enabled'] ?? false) ? '✅ Подтверждение включено' : '❌ Подтверждение отключено', 'callback_data' => "welcome_feedback_settings_confirm_setup_$cabinet->id"]],
                    [['text' => ($settings['autosend']['enabled'] ?? false) ? '✅ Настройка автоотправки' : '❌ Настройка автоотправки', 'callback_data' => "welcome_feedback_settings_autosend_setup_$cabinet->id"]],
                    [['text' => ($settings['recommend_products'] ?? false) ? '✅ Рекомендации включены' : '❌ Рекомендации отключены', 'callback_data' => "welcome_feedback_settings_recommend_$cabinet->id"]],
                    [['text' => ($settings['enabled'] ?? false) ? '✅ Бот включен' : '❌ Бот выключен', 'callback_data' => "welcome_feedback_settings_enabled_$cabinet->id"]],
                    [['text' => '🔙 Назад', 'callback_data' => 'welcome_manage_cabinet']]
                ]);
            } else {
                // If the bot is disabled, only show the "Enable Bot" and "Back" options
                $keyboard = new InlineKeyboardMarkup([
                    [['text' => '❌ Включить бота', 'callback_data' => "welcome_feedback_settings_enabled_$cabinet->id"]],
                    [['text' => '🔙 Назад', 'callback_data' => 'welcome_manage_cabinet']]
                ]);
            }
        } else {
            $message = "Чтобы включить автоматические ответы, нужно подключить чат.
    1️⃣ Создайте чат
    2️⃣ Нажмите на кнопку снизу и выберите нужный чат
    3️⃣ Если бот просит ввести команду, отправьте в чат <code>/start $uniqueCommand</code> (нажмите для копирования)";

            $botUsername = 'wbhelpyfb_bot';
            $link = "https://t.me/{$botUsername}?startgroup=true";
            $keyboard = new InlineKeyboardMarkup([
                [[
                    'text' => '+ Добавить чат',
                    'url' => $link 
                ]],
                [['text' => '🔙 Назад', 'callback_data' => 'welcome_manage_cabinet']]
            ]);
        }

        // Send or update the message with the instructions and options
        $updatedMessage = $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
        Cache::put("add_key_message_id_{$user->telegram_id}", ['action' => 'add_key', 'messageId' => $updatedMessage->getMessageId()], 300); // Cache for 5 minutes
    }
    
    public function handleAddGroup($chatId, $cabinetId, $messageId = null, $isOnboarding = false)
    {
        $user = Auth::user();
        $cabinet = Cabinet::findOrfail($cabinetId);

        // Generate a unique command for adding the bot to the chat
        $uniqueCommand = 'AddReviews_' . $cabinetId;

        $message = "Чтобы включить автоматические ответы, нужно подключить чат.
        1️⃣ Создайте чат
        2️⃣ Нажмите на кнопку снизу и выберите нужный чат
        3️⃣ Если бот просит ввести команду, отправьте в чат <code>/start $uniqueCommand</code> (нажмите для копирования)";
    
        $botUsername = 'wbhelpyfb_bot';
        $link = "https://t.me/{$botUsername}?startgroup=true";
        $keyboard = new InlineKeyboardMarkup([
            [[
                'text' => '+ Добавить чат',
                'url' => $link 
            ]],
            [['text' => '🔙 Назад', 'callback_data' => 'welcome_manage_cabinet']]
        ]);
        // Send or update the message with the instructions and options
        $updatedMessage = $this->sendOrUpdateMessage($chatId, $messageId, $message, $keyboard, 'HTML');
        Cache::put("add_key_message_id_{$user->telegram_id}", ['action' => 'add_key', 'messageId' => $updatedMessage->getMessageId(), 'isOnboarding' => $isOnboarding], 300); // Cache for 5 minutes 
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

    //Setup autosend
    public function handleAutosendSetup($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackAutoSendController = new FeedbackAutoSendController($this->bot);
        $feedbackAutoSendController->setupAutoSend($chatId, $cabinetId, $messageId);
    }

    protected function handleAutosendToggle($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackAutoSendController = new FeedbackAutoSendController($this->bot);
        $feedbackAutoSendController->toggleAutoSend($chatId, $cabinetId, $messageId);
    }

    protected function handleAutosendToggleNoText($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackAutoSendController = new FeedbackAutoSendController($this->bot);
        $feedbackAutoSendController->toggleSendIfNoText($chatId, $cabinetId, $messageId);
    }

    protected function handleAutosendToggleWithText($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackAutoSendController = new FeedbackAutoSendController($this->bot);
        $feedbackAutoSendController->toggleSendIfWithText($chatId, $cabinetId, $messageId);
    }

    public function handleCollectStarRangeAutosend($chatId, $text, $cabinetId, $messageIdOriginal, $messageId){
        $feedbackAutoSendController = new FeedbackAutoSendController($this->bot);
        $feedbackAutoSendController->setStarRangeAutosend($chatId, $cabinetId, $text, $messageIdOriginal, $messageId); 
    }

    //Setup Confirm
    public function handleConfirmSetup($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackConfirmController = new FeedbackConfirmController($this->bot);
        $feedbackConfirmController->setupConfirm($chatId, $cabinetId, $messageId);
    }

    protected function handleConfirmToggle($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackConfirmController = new FeedbackConfirmController($this->bot);
        $feedbackConfirmController->toggleConfirm($chatId, $cabinetId, $messageId);
    }

    protected function handleConfirmToggleNoText($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackConfirmController = new FeedbackConfirmController($this->bot);
        $feedbackConfirmController->toggleSendIfNoText($chatId, $cabinetId, $messageId);
    }

    protected function handleConfirmToggleWithText($chatId, $cabinetId, $settingName, $messageId)
    {
        $feedbackConfirmController = new FeedbackConfirmController($this->bot);
        $feedbackConfirmController->toggleSendIfWithText($chatId, $cabinetId, $messageId);
    }

    public function handleCollectStarRangeConfirm($chatId, $text, $cabinetId, $messageIdOriginal, $messageId){
        $feedbackConfirmController = new FeedbackConfirmController($this->bot);
        $feedbackConfirmController->setStarRangeConfirm($chatId, $cabinetId, $text, $messageIdOriginal, $messageId); 
    }

    protected function handleToggleSetup($chatId, $cabinetId, $settingName, $messageId)
    {
        $user = Auth::user();
        $cabinet = $user->cabinets()->findOrFail($cabinetId);

        Log::info("Cabinet setup in progress: {$chatId}, cabinet ID: {$cabinetId}, settingName: {$settingName}");

        // Since the settings attribute is automatically cast to an array, no need to decode it manually
        $settings = $cabinet->settings;

        // Toggle the specified setting
        $settings[$settingName] = !($settings[$settingName] ?? false);

        // Assign the updated settings array back to the model
        $cabinet->settings = $settings;

        // No need to manually encode settings; Laravel will handle it
        $cabinet->save();

        // Optionally, refresh the manage reviews menu
        $this->handleManageReviews($chatId, $cabinetId, $messageId);
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

    public function setupCabinet($cabinetId, $chatId, $bot, $userTelegramId)
    {
        // Fetch the cabinet by ID
        $cabinet = Cabinet::findOrFail($cabinetId);

        // The settings attribute is automatically cast to an array, so no need to decode it manually
        $settings = $cabinet->settings;

        if (!is_array($settings) || empty($settings)) {
            $settings = []; // Initialize as an empty array only if not already an array or if it's empty
        }

        // Merge the existing settings with the new group_chat_id
        $cabinet->settings = array_merge($settings, [
            'group_chat_id' => $chatId,
            'enabled' => true,
        ]);

        $cabinet->save();

        // Log the event
        Log::info("Cabinet setup completed for chat: {$chatId}, cabinet ID: {$cabinetId}");

        // Send a welcome message to the chat
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '🍓 Перейти в бота', 'url' => 'https://t.me/wbhelpyfb_bot']]
        ]);

        $message = "✅ Успешно подключено";
        $bot->sendMessage($chatId, $message, null, false, null, $keyboard);


        $cachedData = Cache::get("add_key_message_id_{$userTelegramId}");

        if ($cachedData) {
            $messageId = $cachedData['messageId'] ?? null;
            $isOnboarding = $cachedData['isOnboarding'] ?? null;
            if($messageId){
                if($isOnboarding){
                    $welcomeBot->handleCongratulations($userTelegramId, $cabinetId, $messageId);
                } else{
                    $welcomeBot->handleManageReviews($userTelegramId, $cabinetId, $messageId);
                }
                Cache::forget("add_key_message_id_{$userTelegramId}");
            }
        }
        
        Cache::forget("session_{$chatId}");
        Cache::forget("add_key_message_id_{$chatId}");
    }

    public function handleChangeAnswer($chatId, $questionId, $messageId = null)
    {
        $user = Auth::user();
        $question = Feedback::findOrFail($questionId);

        $statusMessage = "Загрузка ответа...";
        $this->updateAnswerMessage($chatId, $question, $statusMessage, $messageId);

        $generatedResponse = $this->generateGptResponse($question);

        $message = $this->formatMessage($question, $generatedResponse);

        Log::info("generatedResponse: {$generatedResponse}");
        Log::info("Change answer for messageId ID: {$messageId}");

        $questionKeyboard = new InlineKeyboardMarkup([
            [['text' => '🔄 Другой', 'callback_data' => "change_answer_{$question->id}"], ['text' => '✅Отправить', 'callback_data' => "accept_answer_{$question->id}"]],
        ]);


        $this->sendOrUpdateMessage($chatId, $messageId, $message, $questionKeyboard, 'HTML');
    }

    public function updateAnswerMessage($chatId, $question, $messageToFormat, $messageId)
    {
        $generatedResponse = $messageToFormat;

        $message = $this->formatMessage($question, $generatedResponse);

        $questionKeyboard = new InlineKeyboardMarkup([
            [['text' => '⏳Загрузка ответа', 'callback_data' => "loading"]],
        ]);

        $this->sendOrUpdateMessage($chatId, $messageId, $message, $questionKeyboard, 'HTML');
    }

    public function formatMessage($question, $generatedResponse)
    {
        $createdDate = Carbon::parse($question['createdDate'])->locale('ru')->isoFormat('LLL');
        $supplierName = str_replace('Индивидуальный предприниматель', 'ИП', $question['productDetails']['supplierName']);
        $supplierName = htmlspecialchars($supplierName);
        $userName = $question['userName']; 
        $productName = htmlspecialchars($question['productDetails']['productName']);
        $article = htmlspecialchars($question['productDetails']['imtId']);
        $questionText = htmlspecialchars($question['text']);
        $generatedResponseText = htmlspecialchars($generatedResponse);

        return "rid_$question->id\n\n<b>Дата:</b> $createdDate\n$supplierName\n<b>Артикул:</b> $article\n<b>📦 Товар:</b> $productName\n\n<b>💬 {$userName}:\n</b>$questionText\n<b>⭐ Оценка:</b> $question->productValuation\n\n<b>🤖 #Предлагаемый_ответ:\n\n</b><code>$generatedResponseText</code>";
    }

    public function generateGptResponse($feedback){
        
        $user = $feedback->cabinet->user;

        if ($user->tokens <= 0) {
            return;
        }
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'max_tokens' => 200,
                'temperature' => 0.5,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник продавца в маркеплейсе Wildberries. Твоя задача давать максимально сгалаженные ответы на вопросы и отзывы под товарами. Твои ответы будут вставлены на сайте. Тебя зовут Алексей. Вопрос пользователя:'],
                    ['role' => 'user', 'content' => $feedback->text],
                    ['role' => 'user', 'content' => "Прошлый ответ не нравится:$feedback->answer"],
                ],
            ]);

            $answer = $response['choices'][0]['message']['content'] ?? null;
            if(!$answer){
                return;
            }
            // Update feedback with the response
            $feedback->update([
                'answer' => $answer,
                'status' => 'ready_to_send',
            ]);
            

            return $answer;

            Log::info('Request to Chat GPT succesfull', ['feedback_id' => $feedback->id, 'answer' => $answer]);
        } catch (\Exception $e) {
            Log::error('Error fetching ChatGPT response: ' . $e->getMessage());
        }
        
    }
}
