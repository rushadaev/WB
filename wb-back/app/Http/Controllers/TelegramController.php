<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TelegramBot\Api\Client;
use TelegramBot\Api\Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class TelegramController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $user = Auth::user();
        
        $bot = new Client(config('telegram.bot_token'));
        $warehouseBot = new WarehouseBotController($bot);

        Log::info('Service Access', [
            'user' => $user,
        ]);
        
        $this->handleCommands($bot, $warehouseBot, $user);
        
        $this->handleMessages($bot, $warehouseBot, $user);
        try {
            $bot->run();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        // Return a response to acknowledge the webhook
        return response()->json(['status' => 'success'], 200);
    }

    protected function isAllowSuppliesFunctions($user){
        if (!Gate::forUser($user)->allows('accessService', 'supplies')) {
            Cache::put("session_{$user->telegram_id}", ['action' => 'collect_wb_suppliers_api_key'], 300); // Cache for 5 minutes
            $this->notify($user->telegram_id, '🗝️ Пожалуйста, предоставьте ваш API-ключ WB "Поставки"');
            return false;
        }
        return true;
    }
    
    protected function isAllowFeedbackFunctions($user){
        if (!Gate::forUser($user)->allows('accessService', 'feedback')) {
            Cache::put("session_{$user->telegram_id}", ['action' => 'collect_wb_feedback_api_key'], 300); // Cache for 5 minutes
            $this->notify($user->telegram_id, '🗝️ Пожалуйста, предоставьте ваш API-ключ WB "Отзывы"');
            return false;
        }
        return true;
    }

    protected function handleCommands(Client $bot, WarehouseBotController $warehouseBot, User $user)
    {
        $bot->command('ping', function ($message) use ($bot, $user) {
            $chatId = $message->getChat()->getId();
            $bot->sendMessage($chatId, 'pong!');
        });

        $bot->command('start', function ($message) use ($warehouseBot, $user) {
            $chatId = $message->getChat()->getId();
            $warehouseBot->handleStart($chatId);
        });
        $bot->command('wb_get', function ($message) use ($warehouseBot, $user) {
            $chatId = $message->getChat()->getId();

            if(!$this->isAllowFeedbackFunctions($user))
                return;
            
            $this->processGetFeedback($chatId);
        }); 
    }

    protected function handleMessages(Client $bot, WarehouseBotController $warehouseBot, User $user)
    {
        $bot->on(function ($update) use ($bot, $warehouseBot, $user) {
            $message = $update->getMessage();
            $callbackQuery = $update->getCallbackQuery();
            $chatId = null;
            $text = null;
            $messageId = null;
    
            if ($message) {
                $chatId = $message->getChat()->getId();
                $text = $message->getText();
                // Process session if there is any action pending
                $this->processSession($chatId, $text, $bot, $update);
            } elseif ($callbackQuery) {
                $chatId = $callbackQuery->getMessage()->getChat()->getId();
                $data = $callbackQuery->getData();
                $messageId = $callbackQuery->getMessage()->getMessageId();
                $this->handleCallbackQuery($chatId, $data, $messageId, $warehouseBot, $user);
                return;
            } else {
                Log::error('Update does not contain a valid message or callback query', ['update' => $update]);
                return;
            } 
            return true;
        }, function () {
            return true;
        });
    }

    protected function handleCallbackQuery($chatId, $data, $messageId, WarehouseBotController $warehouseBot, $user)
    {
        if (strpos($data, 'wh_') === 0) {
            $warehouseBot->handleInlineQuery($chatId, $data, $messageId);
        } else {
            // Add handling for other types of callback queries here
            $this->handleOtherCallbackQueries($chatId, $data, $messageId);
        }
        return response()->json(['status' => 'success'], 200);
    }

    protected function processSession($chatId, $text, Client $bot, $update)
    {
        $session = Cache::get("session_{$chatId}");
    
        if ($session) {
            if (isset($session['action'])) {
                switch ($session['action']) {
                    case 'collect_wb_suppliers_api_key':
                        $this->setApiKey($chatId, $text, 'supplies', $bot);
                        break;
                    case 'collect_wb_feedback_api_key':
                        $this->setApiKey($chatId, $text, 'feedback', $bot);
                        break;
                    default:
                        Log::warning('Unknown action in session', ['action' => $session['action']]);
                        $bot->sendMessage($chatId, 'Неизвестное действие. Пожалуйста, начните заново.');
                        break;
                }
            } else {
                Log::warning('No action found in session', ['session' => $session]);
            }
        } else {
            Log::info('No active session found for chatId', [
                'chatId' => $chatId,
                'text' => $text
            ]);
        }
    }

    protected function setApiKey($chatId, $apiKey, $service, Client $bot)
    {
        $user = Auth::user();
        $user->apiKeys()->updateOrCreate(
            ['service' => $service],
            ['api_key' => $apiKey]
        );
    
        $bot->sendMessage($chatId, "Ваш API-ключ для службы {$service} сохранен. Теперь вы можете использовать команды Wildberries Bot. 🚀");
        $this->clearSession($chatId); // Clear the session cache after setting the key
    }

    protected function clearSession($chatId){
        Cache::forget("session_{$chatId}");
    }
    
    protected function handleOtherCallbackQueries($chatId, $data, $messageId)
    {

        return response()->json(['status' => 'success'], 200);
    }
    

    //legacy delete later
    protected function processCallbackData($chatId, $text, Client $bot, $callbackQuery)
    {
       
    }

    
    protected function getUpdateDetails($update)
    {
        return [
            'update_id' => $update->getUpdateId(),
            'message' => $update->getMessage() ? $this->getMessageDetails($update->getMessage()) : null,
            'callback_query' => $update->getCallbackQuery() ? $this->getCallbackQueryDetails($update->getCallbackQuery()) : null,
            'edited_message' => $update->getEditedMessage() ? $this->getMessageDetails($update->getEditedMessage()) : null,
        ];
    }
    
    protected function getMessageDetails($message)
    {
        return [
            'message_id' => $message->getMessageId(),
            'from' => $message->getFrom() ? $this->getUserDetails($message->getFrom()) : null,
            'chat' => $message->getChat() ? $this->getChatDetails($message->getChat()) : null,
            'date' => $message->getDate(),
            'text' => $message->getText(),
            'photo' => $message->getPhoto(),
            'entities' => $message->getEntities(),
            // Add other relevant fields as needed
        ];
    }
    
    protected function getUserDetails($user)
    {
        return [
            'id' => $user->getId(),
            'is_bot' => $user->isBot(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'username' => $user->getUsername(),
            'language_code' => $user->getLanguageCode(),
        ];
    }
    
    protected function getChatDetails($chat)
    {
        return [
            'id' => $chat->getId(),
            'type' => $chat->getType(),
            'title' => $chat->getTitle(),
            'username' => $chat->getUsername(),
            'first_name' => $chat->getFirstName(),
            'last_name' => $chat->getLastName(),
        ];
    }
    
    protected function getCallbackQueryDetails($callbackQuery)
    {
        return [
            'id' => $callbackQuery->getId(),
            'from' => $callbackQuery->getFrom() ? $this->getUserDetails($callbackQuery->getFrom()) : null,
            'message' => $callbackQuery->getMessage() ? $this->getMessageDetails($callbackQuery->getMessage()) : null,
            'inline_message_id' => $callbackQuery->getInlineMessageId(),
            'chat_instance' => $callbackQuery->getChatInstance(),
            'data' => $callbackQuery->getData(),
            'game_short_name' => $callbackQuery->getGameShortName(),
        ];
    }
    
    protected function logObjectProperties($title, $object)
    {
        if (is_object($object)) {
            $reflection = new \ReflectionClass($object);
            $properties = $reflection->getProperties();
            $propertyValues = [];
    
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $propertyValues[$property->getName()] = $property->getValue($object);
            }
    
            Log::info($title, $propertyValues);
        } else {
            Log::info($title, ['object' => $object]);
        }
    }

    protected function processGetFeedback($chatId)
    {
        $this->notify($chatId, 'Получаем данные с Wildberries 🍓'); 
        $this->fetchAndSendQuestions('single', $chatId);
    }
    
    protected function notify($telegramId, $message)
    {
        $bot = new Client(config('telegram.bot_token'));
        $bot->sendMessage($telegramId, $message);
    }
    
    public function fetchAndSendQuestions($mode, $telegramId)
    {
        
        Artisan::call('fetch:send-questions', [
            'mode' => $mode,
            'telegram_id' => $telegramId
        ]);

        Log::info(['message' => 'Command executed successfully']);
    }
}
