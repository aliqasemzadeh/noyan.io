<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Agents\UserAssistant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessAiComposerPromptJob implements ShouldQueue
{
    use Queueable;

    public const CACHE_KEY_PREFIX = 'ai-composer-prompt:';

    public const SYNC_MAX_CHARS = 2000;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $userId,
        public ?int $businessId,
        public string $context,
        public string $prompt,
        public ?string $conversationId,
        public string $resultToken,
    ) {
        $this->afterCommit();
    }

    public static function cacheKey(string $token): string
    {
        return self::CACHE_KEY_PREFIX.$token;
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            $this->storeResult([
                'status' => 'failed',
                'reply' => __('general.ai_error'),
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        if ($this->businessId !== null && (int) $user->current_business_id !== $this->businessId) {
            $user->forceFill(['current_business_id' => $this->businessId])->save();
        }

        Auth::login($user);

        try {
            if ($this->context === 'users') {
                $response = (new UserAssistant)->prompt($this->prompt);
                $conversationId = $this->conversationId;
            } else {
                $response = (new AccountingAssistant)
                    ->continueOrStart($this->conversationId, $user)
                    ->prompt($this->prompt);
                $conversationId = $response->conversationId ?? $this->conversationId;
            }

            $this->storeResult([
                'status' => 'done',
                'reply' => $response->text ?: __('general.ai_reply_empty'),
                'conversation_id' => $conversationId,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->storeResult([
                'status' => 'failed',
                'reply' => __('general.ai_error'),
                'conversation_id' => $this->conversationId,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);

        $this->storeResult([
            'status' => 'failed',
            'reply' => __('general.ai_error'),
            'conversation_id' => $this->conversationId,
        ]);
    }

    /**
     * @param  array{status: string, reply: string, conversation_id: ?string}  $payload
     */
    protected function storeResult(array $payload): void
    {
        Cache::put(self::cacheKey($this->resultToken), $payload, now()->addHour());
    }
}
