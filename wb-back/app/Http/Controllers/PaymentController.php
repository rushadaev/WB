<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramNotificationService;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use App\Models\User;
use Carbon\Carbon;
use App\Jobs\SendUserNotificationMessage;
use App\Traits\UsesYooKassa;

class PaymentController extends Controller
{
    use UsesYooKassa;

    public function __construct()
    {
        $this->initializeYooKassa();
    }

    public function createPaymentLink($amount, $orderId, $telegramId, $description, $subscriptionPeriod)
    {
        try{
            $url = $this->yooKassaService->createPaymentLink($amount, $orderId, $telegramId, $description, $subscriptionPeriod);
        } catch (\Exception $e) {
            $url = $e;
            Log::error($e->getMessage());
        }
        return $url;
    }

    public function createPaymentLinkTest($amount, $orderId, $telegramId, $description, $subscriptionPeriod)
    {
        try{
            $url = $this->yooKassaService->createPaymentLinkTest($amount, $orderId, $telegramId, $description, $subscriptionPeriod);
        } catch (\Exception $e) {
            $url = $e;
            Log::error($e->getMessage());
        }
        return $url;
    }

    public function paymentReturn(Request $request)
    {
        $orderId = $request->orderId;
        // Retrieve payment ID from cache
        $paymentId = Cache::get("payment_id_{$orderId}");
    
        if (!$paymentId) {
            return redirect()->away('https://t.me/wbhelpyfb_bot');
        }
    
        // Retrieve payment details from YooKassa
        $payment = $this->yooKassaService->retrievePayment($paymentId);
    
        Log::info('Payment details', $payment->jsonSerialize());
        if ($payment->status === 'succeeded') {
            $this->handlePaymentSuccess($payment); 
            // Handle payment success
            return redirect()->away('https://t.me/wbhelpyfb_bot');
        } else {
            // Handle payment failure
            return redirect()->away('https://t.me/wbhelpyfb_bot');
        }
    }

    public function paymentSuccess(Request $request)
    {
        $requestBody = $request->all();
        // Use the YooKassaService to handle the webhook data
        $payment = $this->yooKassaService->handleWebhook($requestBody);
        $this->handlePaymentSuccess($payment); 
    }

    public function handlePaymentSuccess($payment){
        if ($payment) {
            // Extract the telegram_id from the payment metadata
            $telegramId = $payment->getMetadata()->telegram_id ?? null;
            $tokens = $payment->getMetadata()->tokens ?? null;
            $message = 'Вы купили токены:';

            if ($telegramId && $tokens) {
                // Find the user by telegram_id
                $user = User::where('telegram_id', $telegramId)->first();
                $user->tokens ??= 0;
                if ($user) {
                    $tokensMessage = str_replace(['_tokens'], '', $tokens);
                    switch ($tokens) {
                        case '100_tokens':
                            $user->tokens += 100;
                            break;
                        case '500_tokens':
                            $user->tokens += 500;
                            break;
                        case '1000_tokens':
                            $user->tokens += 1000;
                            break;
                        case '5000_tokens':
                            $user->tokens += 5000;
                            break;
                        case '10000_tokens':
                            $user->tokens += 10000;
                            break;
                    }

                    $user->save();
                    $message = $message.' '.$tokensMessage.' шт.';
                    $keyboard = new InlineKeyboardMarkup([
                        [['text' => '🏠 На главную', 'callback_data' => 'wh_main_menu']] 
                    ]);
                    TelegramNotificationService::notify($telegramId, $message, config('telegram.bot_token'), $keyboard);
                    Log::info('User tokens updated', ['user' => $user, 'tokens' => $tokens]);
                } else {
                    Log::warning('User not found for telegram_id', ['telegram_id' => $telegramId]);
                }
            } else {
                Log::warning('Telegram ID or subscription period not found in payment metadata', ['paymentinfo' => $payment]);
            }
            Log::info('Payment success', ['paymentinfo' => $payment]);
            return response()->json(['message' => 'Payment successful']);
        } else {
            Log::info('Webhook failed', ['WebhookInfo' => $requestBody]);
            return response()->json(['message' => 'Payment processing error'], 400);
        }
    }
}