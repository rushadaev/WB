<?php

namespace App\Jobs;

use App\Models\Feedback;
use App\Services\ChatGptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateChatGptResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $feedback;

    /**
     * Create a new job instance.
     */
    public function __construct(Feedback $feedback)
    {
        $this->feedback = $feedback;
    }

    /**
     * Execute the job.
     */
    public function handle(ChatGptService $chatGptService): void
    {
        $chatGptService->generateResponse($this->feedback);
    }
}