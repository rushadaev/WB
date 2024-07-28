<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\TelegramInspire;
Artisan::command('inspire', function () {
    // Get the inspiring quote
    $quote = Inspiring::quote();

    // Remove any HTML tags from the quote
    $cleanedQuote = strip_tags($quote);

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

    // Dispatch the job to send the message to Telegram
    TelegramInspire::dispatch('782919745', $message, 'MarkdownV2');
})->purpose('Display an inspiring quote')->hourly();

