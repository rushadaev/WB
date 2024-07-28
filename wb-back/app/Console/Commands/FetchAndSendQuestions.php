<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WildberriesService;
use App\Jobs\SendTelegramMessage;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;

class FetchAndSendQuestions extends Command
{
    protected $signature = 'fetch:send-questions {mode=single}'; // Mode can be 'single' or 'multiple'
    protected $description = 'Fetch questions from Wildberries and send them to Telegram';
    protected $wildberriesService;

    public function __construct(WildberriesService $wildberriesService)
    {
        parent::__construct();
        $this->wildberriesService = $wildberriesService;
    }

    public function handle()
    {
        $mode = $this->argument('mode');
        $response = $this->wildberriesService->getQuestions();

        if ($response['error']) {
            Log::error('Failed to fetch questions from Wildberries API', [
                'errorText' => $response['errorText']
            ]);
            return;
        }

        $questions = $response['data']['questions'];
        $chatId = config('telegram.default_user_id'); 

        if ($mode === 'single') {
            // Send one question
            $question = reset($questions);
            $this->dispatchJob($question, $chatId);
        } else {
            // Send multiple questions (3 per day)
            $questionsToSend = array_slice($questions, -3);
            foreach ($questionsToSend as $question) {
                $this->dispatchJob($question, $chatId);
            }
        }
    }

    protected function dispatchJob($question, $chatId)
    {
        $generatedResponse = $this->generateGptResponse($question['text'].'Товар:'.$question['productDetails']['productName']);
        $message = $this->formatMessage($question, $generatedResponse);
        SendTelegramMessage::dispatch($chatId, $message, 'HTML'); 
        // SendTelegramMessage::dispatch($chatId, $message, 'MarkdownV2')->delay(now()->addMinutes(rand(1, 2))); // Delay jobs to spread out messages
    }

    protected function generateGptResponse($message) {
        $htmlMarkupInstructions = "
    <b>bold</b>, <strong>bold</strong>
    <i>italic</i>, <em>italic</em>
    <u>underline</u>, <ins>underline</ins>
    <s>strikethrough</s>, <strike>strikethrough</strike>, <del>strikethrough</del>
    <span class='tg-spoiler'>spoiler</span>, <tg-spoiler>spoiler</tg-spoiler>
    <b>bold <i>italic bold <s>italic bold strikethrough <span class='tg-spoiler'>italic bold strikethrough spoiler</span></s> <u>underline italic bold</u></i> bold</b>
    <a href='http://www.example.com/'>inline URL</a>
    <a href='tg://user?id=123456789'>inline mention of a user</a>
    <tg-emoji emoji-id='5368324170671202286'>👍</tg-emoji>
    <code>inline fixed-width code</code>
    <pre>pre-formatted fixed-width code block</pre>
    <pre><code class='language-python'>pre-formatted fixed-width code block written in the Python programming language</code></pre>
    <blockquote>Block quotation started\nBlock quotation continued\nThe last line of the block quotation</blockquote>
    <blockquote expandable>Expandable block quotation started\nExpandable block quotation continued\nExpandable block quotation continued\nHidden by default part of the block quotation started\nExpandable block quotation continued\nThe last line of the block quotation</blockquote>";
    
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'max_tokens' => 200,
            'messages' => [
                ['role' => 'system', 'content' => 'Ты помощник продавца в маркеплейсе Wildberries. Твоя задача давать максимально сгалаженные ответы на вопросы и отзывы под товарами. Твои ответы будут вставлены на сайте. Тебя зовут Алексей. Можно использовать HTML Markup по инструкции:' . $htmlMarkupInstructions . ' Вопрос пользователя:'],
                ['role' => 'user', 'content' => $message],
            ],
        ]);
    
        $generatedResponse = $result->choices[0]->message->content;

        // Replacing <br> with \n
        $generatedResponse = str_replace('<br>', '\n', $generatedResponse);
    
        // Replacing **text** with <b>text</b>
        $generatedResponse = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $generatedResponse);
    
        return $generatedResponse;
    }

    protected function formatMessage($question, $generatedResponse)
    {
        $createdDate = Carbon::parse($question['createdDate'])->locale('ru')->isoFormat('LLL');
        $productName = htmlspecialchars($question['productDetails']['productName']);
        $questionText = htmlspecialchars($question['text']);
        $generatedResponseText = $generatedResponse;
    
        return "<b>Дата:</b> $createdDate\n<b>Товар:</b> $productName\n<b>Вопрос:</b> $questionText\n<b>Ответ GPT:</b> $generatedResponseText";
    }
}