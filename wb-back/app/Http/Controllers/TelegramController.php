<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TelegramBot\Api\Client;
use TelegramBot\Api\Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class TelegramController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $bot = new Client(config('telegram.bot_token'));

        $this->handleCommands($bot);
        $this->handleMessages($bot);

        try {
            $bot->run();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    protected function handleCommands(Client $bot)
    {
        $bot->command('ping', function ($message) use ($bot) {
            $bot->sendMessage($message->getChat()->getId(), 'pong!');
        });
    }

    protected function handleMessages(Client $bot)
    {
        $bot->on(function ($update) use ($bot) {
            $message = $update->getMessage();
            $callbackQuery = $update->getCallbackQuery();
            
            if ($message) {
                $chatId = $message->getChat()->getId();
                $text = $message->getText();
            } elseif ($callbackQuery) {
                $chatId = $callbackQuery->getMessage()->getChat()->getId();
                $text = $callbackQuery->getData();
            } else {
                Log::error('Update does not contain a valid message or callback query', ['update' => $update]);
                return;
            }
    
            // $this->processSession($chatId, $text, $bot, $update);
    
            $this->processCallbackData($chatId, $text, $bot, $callbackQuery);
        }, function () {
            return true;
        });
    }


    protected function processSession($chatId, $text, Client $bot, $update)
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

  
    protected function processCallbackData($chatId, $text, Client $bot, $callbackQuery)
    {
        if (strpos($text, 'wb_get') !== false) {
            $this->processGetFeedback($chatId, $text, $bot, $callbackQuery);
        }
    }

    protected function processGetFeedback($chatId, $text, Client $bot, $callbackQuery)
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