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
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

use App\Jobs\DispatchFeedbackSendJobs;

Artisan::command('inspire', function () {
    // Get the inspiring quote
    $quote = Inspiring::quote();

    $this->comment($quote);
    $result = OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'max_tokens' => 200,
        'temperature' => 0.3,
        'messages' => [
            ['role' => 'system', 'content' => 'You are highly encouraged to provide an inspiring quote.'],
            ['role' => 'user', 'content' => 'Provide quote based on current time' . date('Y-m-d H:i:s'). ' and this quote: '.$quote],
        ],
    ]);
    

    $generatedResponse = $result->choices[0]->message->content;

    $this->comment($generatedResponse);
    // Remove any HTML tags from the quote
    $cleanedQuote = strip_tags($generatedResponse);

    // Escape Telegram markdown special characters
    $escapedQuote = str_replace(
        ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
        ['\_', '\*', '$begin:math:display$', '$end:math:display$', '$begin:math:text$', '$end:math:text$', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!'],
        $cleanedQuote
    );

    // Extract the quote and author
    if (preg_match('/^(.*)\n\s*—\s*(.*)$/s', $escapedQuote, $matches)) {
        $quoteText = trim($matches[1]);
        $author = trim($matches[2]);
    } else {
        // Default fallback if the pattern doesn't match
        $quoteText = $escapedQuote;
        $author = 'Unknown';
    }

    // Format the message for Telegram markdown
    $message = sprintf("> %s\n\n— _%s_", $quoteText, $author);
    // Display the formatted message in the console
    $this->comment($message);

    $token = config('telegram.bot_token_test');
    $channel = config('telegram.channel_test');
    // Dispatch the job to send the message to Telegram
    TelegramInspire::dispatch($channel, $message, 'MarkdownV2', $token);
})->purpose('Display an inspiring quote')->everyThreeHours();


Artisan::command('feedback_send', function () {
    $cabinets = Cabinet::all();
    foreach ($cabinets as $cabinet) {
        SendFeedbacksToTelegramJob::dispatch($cabinet->id);
    }
})->purpose('Feedback fetch')->hourly();