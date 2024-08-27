<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\TelegramInspire;
use App\Jobs\CheckCoefficientChanges;
use App\Jobs\FetchWarehouseCoefficientsJob;
use App\Jobs\CheckSubscriptionExpiration;
use App\Jobs\SendFeedbacksToTelegramJob;
use App\Jobs\FetchFeedbacksJob;
use App\Models\Cabinet;
use App\Models\Notification;
use App\Models\User;

// Artisan::command('inspire', function () {
//     // Get the inspiring quote
//     $quote = Inspiring::quote();

//     // Remove any HTML tags from the quote
//     $cleanedQuote = strip_tags($quote);

//     // Escape Telegram markdown special characters
//     $escapedQuote = str_replace(
//         ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
//         ['\_', '\*', '$begin:math:display$', '$end:math:display$', '$begin:math:text$', '$end:math:text$', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!'],
//         $cleanedQuote
//     );

//     // Extract the quote and author
//     if (preg_match('/^(.*)\n\s*—\s*(.*)$/s', $escapedQuote, $matches)) {
//         $quoteText = trim($matches[1]);
//         $author = trim($matches[2]);
//     } else {
//         // Default fallback if the pattern doesn't match
//         $quoteText = $escapedQuote;
//         $author = 'Unknown';
//     }

//     // Format the message for Telegram markdown
//     $message = sprintf("> %s\n\n— _%s_", $quoteText, $author);
//     // Display the formatted message in the console
//     $this->comment($message);

//     $token = config('telegram.bot_token_test');
//     $channel = config('telegram.channel_test');
//     // Dispatch the job to send the message to Telegram
//     TelegramInspire::dispatch($channel, $message, 'MarkdownV2', $token);
// })->purpose('Display an inspiring quote')->hourly();

Artisan::command('warehouse_bot', function () {
    CheckCoefficientChanges::dispatch(config('telegram.bot_token_supplies'));
})->purpose('Fetch coefficients and check for changes')->everyMinute();

// Artisan::command('warehouse_bot_fetch_coefficients', function () {
//     FetchWarehouseCoefficientsJob::dispatch();
// })->purpose('Fetch updated coefficients from WB')->everyMinute();


Artisan::command('warehouse_bot_check_subscription_expiration', function () {
    CheckSubscriptionExpiration::dispatch();
})->purpose('Check subscription expiration')->hourly();


// Artisan::command('feedback_fetch', function () {
//     $cabinets = Cabinet::all();
//     foreach ($cabinets as $cabinet) {
//         FetchFeedbacksJob::dispatch($cabinet->id);
//     }
// })->purpose('Feedback fetch')->hourly();

// Artisan::command('feedback_send', function () {
//     $cabinets = Cabinet::all();
//     foreach ($cabinets as $cabinet) {
//         SendFeedbacksToTelegramJob::dispatch($cabinet->id);
//     }
// })->purpose('Feedback fetch')->hourly();