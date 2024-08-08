<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramNotificationService;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use App\Models\User;
use Carbon\Carbon;
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

    public function paymentReturn(Request $request)
    {
        $orderId = $request->orderId;
        // Retrieve payment ID from cache
        $paymentId = Cache::get("payment_id_{$orderId}");
    
        if (!$paymentId) {
            return redirect()->away('https://t.me/wbhelpy_bot');
        }
    
        // Retrieve payment details from YooKassa
        $payment = $this->yooKassaService->retrievePayment($paymentId);
    
        Log::info('Payment details', $payment->jsonSerialize());
        if ($payment->status === 'succeeded') {
            return redirect()->away('https://t.me/wbhelpy_bot');
        } else {
            // Handle payment failure
            return redirect()->away('https://t.me/wbhelpy_bot');
        }
    }

    public function paymentSuccess(Request $request)
    {
        $requestBody = $request->all();
        // Use the YooKassaService to handle the webhook data
        $payment = $this->yooKassaService->handleWebhook($requestBody);
        
        if ($payment) {
            // Extract the telegram_id from the payment metadata
            $telegramId = $payment->getMetadata()->telegram_id ?? null;
            $subscriptionPeriod = $payment->getMetadata()->subscription_period ?? null;
            $message = 'Подписка продлена до ';

            if ($telegramId && $subscriptionPeriod) {
                // Find the user by telegram_id
                $user = User::where('telegram_id', $telegramId)->first();

                if ($user) {
                    // Determine the number of days to add based on the subscription period
                    switch ($subscriptionPeriod) {
                        case 'pay_1_week':
                            $daysToAdd = 7;
                            break;
                        case 'pay_1_month':
                            $daysToAdd = 30;
                            break;
                        case 'pay_3_months':
                            $daysToAdd = 90;
                            break;
                        case 'pay_6_months':
                            $daysToAdd = 180;
                            break;
                        case 'pay_forever':
                            $daysToAdd = 36500; // Approximately 100 years
                            break;
                        default:
                            $daysToAdd = 0;
                            break;
                    }

                    if ($daysToAdd > 0) {
                        // Add the calculated days to the subscription_until field
                        $user->subscription_until = Carbon::parse($user->subscription_until)->addDays($daysToAdd);
                        $user->is_paid = 1;
                        $user->save();

                        $formattedDate = Carbon::parse($user->subscription_until)->format('d-m-Y');
                        $message = $message.''.$formattedDate.' Спасибо!';
                        if($subscriptionPeriod == 'pay_forever'){
                            $message = 'Вы купили доступ навсегда! Спасибо!';
                        }
                        $keyboard = new InlineKeyboardMarkup([
                            [['text' => '🏠 На главную', 'callback_data' => 'wh_main_menu']] 
                        ]);
                        TelegramNotificationService::notify($telegramId, $message, config('telegram.bot_token_supplies'), $keyboard);
                        Log::info('User subscription updated', ['user' => $user, 'days_added' => $daysToAdd]);
                    } else {
                        Log::warning('Invalid subscription period', ['subscription_period' => $subscriptionPeriod]);
                    }
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